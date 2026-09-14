<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelProvider;
use App\Services\Travel\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class TravelLocationController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate(['search' => ['required', 'string', 'min:3', 'max:190'], 'locale' => ['required', 'in:hy,ru,en'], 'kind' => ['required', 'in:city,address']]);
        $provider = TravelProvider::where('code', 'geoapify')->where('enabled', true)->where('environment', 'live')->first();
        if (! $provider || ! ProviderRegistry::ready($provider)) {
            return response()->json(['data' => [], 'available' => false])->header('Cache-Control', 'no-store');
        }
        $limit = 'travel-provider:geoapify:'.now()->toDateString();
        if (RateLimiter::tooManyAttempts($limit, (int) config('travel.daily_search_limit'))) {
            return response()->json(['data' => [], 'available' => false])->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($limit, 86400);
        try {
            $results = Http::acceptJson()->connectTimeout(2)->timeout(5)->withoutRedirecting()->get('https://api.geoapify.com/v1/geocode/autocomplete', [
                'text' => $input['search'], 'lang' => $input['locale'] === 'ru' ? 'ru' : 'en', 'format' => 'json', 'limit' => 5, 'apiKey' => $provider->credentials['api_key'],
                ...($input['kind'] === 'city' ? ['type' => 'city'] : []),
            ])->throw()->json('results');
            $data = collect($results)->filter(fn ($p) => isset($p['formatted'], $p['lat'], $p['lon']))->map(fn ($p) => ['label' => $p['formatted'], 'latitude' => $p['lat'], 'longitude' => $p['lon']])->values();

            return response()->json(['data' => $data, 'available' => true])->header('Cache-Control', 'no-store');
        } catch (\Throwable $error) {
            return response()->json(['data' => [], 'available' => false])->header('Cache-Control', 'no-store');
        }
    }
}
