<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Service;
use App\Models\Tour;
use App\Models\TravelProvider;
use App\Services\Travel\PackageCatalog;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\ProviderSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TravelSearchController extends Controller
{
    public const TYPES = ['tours' => 'tour', 'hotels' => 'accommodation', 'flights' => 'flight', 'cars' => 'transport', 'transfers' => 'transfer', 'activities' => 'activity', 'places' => 'place', 'packages' => 'package'];

    public function services()
    {
        return ['services' => array_map(fn ($key, $type) => ['key' => $key, 'request_type' => $type], array_keys(self::TYPES), array_values(self::TYPES)), 'payments_enabled' => false];
    }

    public function search(Request $request, ProviderSearch $search)
    {
        $input = $request->validate([
            'service' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'locale' => ['required', 'in:hy,ru,en'],
            'origin' => ['nullable', 'string', 'max:190'],
            'destination' => ['nullable', 'string', 'max:190'],
            'start_date' => ['nullable', 'required_if:service,flights,hotels,transfers,cars', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(365)->toDateString()],
            'end_date' => ['nullable', 'required_if:service,hotels,cars', 'required_with:rooms', 'date_format:Y-m-d', $request->input('service') === 'hotels' ? 'after:start_date' : 'after_or_equal:start_date', 'before_or_equal:'.now()->addDays(365)->toDateString()],
            'departure_time' => ['nullable', 'required_if:service,transfers', 'date_format:H:i'],
            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['sometimes', 'array', 'max:6'],
            'children.*' => ['integer', 'min:0', 'max:17'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:9', 'lte:adults'],
            'cabin' => ['nullable', 'in:economy,premium_economy,business,first'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'booker_country' => ['nullable', 'required_if:service,hotels', 'regex:/^[A-Za-z]{2}$/'],
            'platform' => ['nullable', 'in:desktop,mobile,tablet'],
            'currency' => ['nullable', 'in:USD,EUR,GBP'],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        if ($input['service'] === 'flights') {
            $request->validate(['origin' => ['required', 'regex:/^[A-Z]{3}$/'], 'destination' => ['required', 'regex:/^[A-Z]{3}$/', 'different:origin']]);
        }
        if (in_array($input['service'], ['transfers', 'activities'])) {
            $request->validate(['destination' => ['required', 'string']]);
        }

        $local = $this->local($input);
        $remote = [];
        $sources = [];
        foreach (TravelProvider::where('enabled', true)->where('environment', 'live')->get() as $provider) {
            if (in_array($provider->code, PackageCatalog::PROVIDERS)) {
                continue;
            }
            if (! in_array($input['service'], ProviderRegistry::definition($provider->code)['services']) || ! ProviderRegistry::ready($provider)) {
                continue;
            }
            if (in_array($provider->code, ['booking', 'geoapify', 'hotelbeds']) && ! isset($input['latitude'], $input['longitude'])) {
                $sources[] = ['name' => ProviderRegistry::definition($provider->code)['name'], 'status' => 'location_required'];

                continue;
            }
            if ($provider->code === 'hotelbeds' && ! preg_match('/^[A-Z]{3}$/', $input['origin'] ?? '')) {
                $sources[] = ['name' => 'Hotelbeds Transfers', 'status' => 'airport_required'];

                continue;
            }
            $limitKey = 'travel-provider:'.$provider->code.':'.now()->toDateString();
            if (RateLimiter::tooManyAttempts($limitKey, (int) config('travel.daily_search_limit'))) {
                $sources[] = ['name' => ProviderRegistry::definition($provider->code)['name'], 'status' => 'temporarily_unavailable'];

                continue;
            }
            RateLimiter::hit($limitKey, 86400);
            try {
                $items = $search->search($provider, $input);
                foreach ($items as $item) {
                    $remote[] = [...$item, 'provider' => $provider->code, 'source' => ProviderRegistry::definition($provider->code)['name'], 'type' => self::TYPES[$input['service']], 'action' => ($item['url'] ?? null) ? 'redirect' : ($provider->code === 'geoapify' ? 'information' : 'request')];
                }
                $sources[] = ['name' => ProviderRegistry::definition($provider->code)['name'], 'status' => 'available'];
                $provider->update(['status' => 'connected', 'last_checked_at' => now(), 'last_success_at' => now(), 'last_error' => null]);
            } catch (\Throwable $error) {
                $sources[] = ['name' => ProviderRegistry::definition($provider->code)['name'], 'status' => 'temporarily_unavailable'];
                $provider->update(['status' => 'error', 'last_checked_at' => now(), 'last_error' => 'Որոնման հարցումը չհաջողվեց։']);
            }
        }

        return response()->json(['data' => [...$local['data'], ...$remote], 'meta' => $local['meta'], 'sources' => $sources, 'request_available' => true, 'payments_enabled' => false])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    private function local(array $input): array
    {
        $service = $input['service'];
        if ($service === 'flights') {
            return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]];
        }
        $type = self::TYPES[$service];
        $query = match ($service) {
            'tours' => Tour::query(), 'packages' => Tour::where('type', 'package'),
            'places' => Destination::query(), default => Service::where('type', $type),
        };
        $locale = $input['locale'];
        $query->where('active', true);
        if (filled($input['destination'] ?? null)) {
            $term = '%'.trim($input['destination']).'%';
            $query->where(fn ($q) => $q->where('title->'.$locale, 'like', $term)->orWhere('description->'.$locale, 'like', $term));
        }
        $page = $query->orderBy('sort_order')->paginate(12, ['*'], 'page', $input['page'] ?? 1);
        $data = collect($page->items())->map(function ($item) use ($locale, $service, $type) {
            $data = $item->toLocalizedArray($locale);
            $path = match ($service) {
                'tours', 'packages' => 'tours', 'hotels' => 'stays', 'cars' => 'cars', 'places' => 'destinations', default => 'services'
            };

            return ['id' => (string) $item->id, 'item_id' => $item->id, 'provider' => 'local', 'source' => 'GoVista', 'title' => $data['title'], 'description' => $data['description'] ?? '', 'image' => $data['image'] ?? null, 'price' => $data['price'] ?? $data['price_from'] ?? null, 'currency' => $data['currency'] ?? null, 'price_basis' => 'from', 'type' => $data['type'] ?? $type, 'url' => '/'.$locale.'/'.$path.'/'.$item->slug, 'action' => 'details'];
        })->all();

        return ['data' => $data, 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }
}
