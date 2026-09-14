<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PackageOffer;
use App\Models\TravelProvider;
use App\Services\Travel\PackageCatalog;
use App\Services\Travel\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function options()
    {
        $tourvisor = TravelProvider::where('code', 'tourvisor')->first();

        return response()->json([
            'destinations' => collect(PackageCatalog::DESTINATIONS)->map(fn ($data, $code) => ['code' => $code, ...$data])->values(),
            'providers' => array_map(fn ($code) => ['code' => $code, 'name' => ProviderRegistry::definition($code)['name']], PackageCatalog::PROVIDERS),
            'live_search' => $tourvisor && $tourvisor->enabled && $tourvisor->environment === 'live' && ProviderRegistry::ready($tourvisor) && PackageCatalog::approved($tourvisor),
            'meals' => PackageCatalog::MEALS, 'payments_enabled' => false,
            'public_url' => rtrim(config('travel.frontend_url'), '/').'/hy/travel/packages',
        ])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'in:hy,ru,en'], 'provider' => ['nullable', Rule::in(['local', ...PackageCatalog::PROVIDERS])],
            'destination_code' => ['nullable', Rule::in(array_keys(PackageCatalog::DESTINATIONS))],
            'origin' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'nights_min' => ['nullable', 'integer', 'between:1,28'], 'nights_max' => ['nullable', 'integer', 'between:1,28', 'gte:nights_min'],
            'adults' => ['nullable', 'integer', 'between:1,9'], 'children' => ['sometimes', 'array', 'max:6'], 'children.*' => ['integer', 'between:0,17'],
            'meal_plan' => ['nullable', Rule::in(PackageCatalog::MEALS)], 'stars' => ['nullable', 'integer', 'between:1,5'],
            'currency' => ['nullable', 'in:AMD,USD,EUR,RUB,GBP,AED'], 'max_price' => ['nullable', 'required_with:currency', 'numeric', 'between:1,999999999.99'],
            'sort' => ['nullable', 'in:departure,price_asc,price_desc'], 'page' => ['nullable', 'integer', 'between:1,1000'],
        ]);
        $query = PackageOffer::published();
        foreach (['provider' => 'provider_code', 'destination_code' => 'destination_code', 'origin' => 'origin', 'adults' => 'adults', 'meal_plan' => 'meal_plan', 'currency' => 'currency'] as $key => $column) {
            if (filled($data[$key] ?? null)) {
                $query->where($column, $data[$key]);
            }
        }
        foreach (['date_from' => ['departure_date', '>='], 'date_to' => ['departure_date', '<='], 'nights_min' => ['nights', '>='], 'nights_max' => ['nights', '<='], 'stars' => ['stars', '>='], 'max_price' => ['price', '<=']] as $key => [$column, $operator]) {
            if (filled($data[$key] ?? null)) {
                $query->where($column, $operator, $data[$key]);
            }
        }
        if (isset($data['adults'])) {
            // Match ages without depending on JSON key order or database-specific JSON sorting.
            $ages = $data['children'] ?? [];
            sort($ages);
            $query->whereJsonLength('children', count($ages));
            foreach (array_values($ages) as $index => $age) {
                $query->where('children->'.$index, (int) $age);
            }
        }
        // Prices in different currencies/bases cannot be ordered as comparable amounts.
        if (in_array($data['sort'] ?? '', ['price_asc', 'price_desc'])) {
            $query->orderBy('currency')->orderBy('price_basis')->orderByRaw('price IS NULL')->orderBy('price', $data['sort'] === 'price_asc' ? 'asc' : 'desc');
        }
        $page = $query->orderBy('departure_date')->orderBy('id')->paginate(12);

        return response()->json(['data' => collect($page->items())->map(fn ($offer) => PackageCatalog::publicOffer($offer, $data['locale'])), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, int $id)
    {
        $locale = $request->validate(['locale' => ['required', 'in:hy,ru,en']])['locale'];

        return response()->json(PackageCatalog::publicOffer(PackageOffer::published()->findOrFail($id), $locale))->header('Cache-Control', 'no-store');
    }
}
