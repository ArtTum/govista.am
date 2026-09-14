<?php

namespace App\Services\Travel;

use App\Models\TravelProvider;
use Illuminate\Support\Facades\Http;

/** Search only. No payment, order creation or ticket issuance calls are made here. */
class ProviderSearch
{
    public function search(TravelProvider $provider, array $input): array
    {
        $http = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(9)->withoutRedirecting();
        $key = $provider->credentials['api_key'];
        $locale = $input['locale'] === 'ru' ? 'ru' : 'en';
        $adults = (int) ($input['adults'] ?? 2);
        $children = $input['children'] ?? [];
        $lat = $input['latitude'] ?? 40.1811;
        $lon = $input['longitude'] ?? 44.5136;
        $page = $input['page'] ?? 1;

        if ($provider->code === 'duffel') {
            $slices = [['origin' => $input['origin'], 'destination' => $input['destination'], 'departure_date' => $input['start_date']]];
            if (! empty($input['end_date'])) {
                $slices[] = ['origin' => $input['destination'], 'destination' => $input['origin'], 'departure_date' => $input['end_date']];
            }
            $passengers = array_merge(array_fill(0, $adults, ['type' => 'adult']), array_map(fn ($age) => ['age' => (int) $age], $children));
            $response = $http->withToken($key)->withHeaders(['Duffel-Version' => 'v2'])->post('https://api.duffel.com/air/offer_requests?return_offers=true&supplier_timeout=5000', ['data' => ['slices' => $slices, 'passengers' => $passengers, 'cabin_class' => $input['cabin'] ?? 'economy']])->throw()->json();
            if (! is_array(data_get($response, 'data.offers'))) {
                throw new \RuntimeException('Invalid supplier response');
            }

            return collect($response['data']['offers'])->take(20)->map(function ($offer) {
                $segments = collect($offer['slices'] ?? [])->flatMap(fn ($slice) => $slice['segments'] ?? []);

                return ['id' => $offer['id'], 'title' => $offer['owner']['name'] ?? 'Flight', 'description' => $segments->map(fn ($s) => ($s['origin']['iata_code'] ?? '').' → '.($s['destination']['iata_code'] ?? '').' · '.($s['departing_at'] ?? '').' · '.($s['operating_carrier']['name'] ?? ''))->implode(' | '), 'price' => $offer['total_amount'] ?? null, 'currency' => $offer['total_currency'] ?? null, 'price_basis' => 'total', 'expires_at' => $offer['expires_at'] ?? null, 'image' => null, 'url' => null];
            })->all();
        }

        if ($provider->code === 'geoapify') {
            $response = $http->get('https://api.geoapify.com/v2/places', ['categories' => 'tourism.sights', 'filter' => "circle:$lon,$lat,10000", 'limit' => 20, 'offset' => ($page - 1) * 20, 'lang' => $locale, 'apiKey' => $key])->throw()->json();
            if (! is_array($response['features'] ?? null)) {
                throw new \RuntimeException('Invalid supplier response');
            }

            return collect($response['features'])->map(function ($feature) {
                $p = $feature['properties'];

                return ['id' => $p['place_id'], 'title' => $p['name'] ?? $p['address_line1'] ?? 'Place', 'description' => $p['formatted'] ?? '', 'price' => null, 'image' => null, 'url' => null, 'latitude' => $p['lat'] ?? null, 'longitude' => $p['lon'] ?? null, 'attribution' => 'Powered by Geoapify · © OpenStreetMap contributors'];
            })->all();
        }

        if ($provider->code === 'viator') {
            $host = $provider->environment === 'live' ? 'https://api.viator.com' : 'https://api.sandbox.viator.com';
            $response = $http->withHeaders(['exp-api-key' => $key, 'Accept-Language' => $locale, 'Accept' => 'application/json;version=2.0'])->post($host.'/partner/search/freetext', [
                'searchTerm' => $input['destination'], 'searchTypes' => [['searchType' => 'PRODUCTS', 'pagination' => ['start' => ($page - 1) * 20 + 1, 'count' => 20]]], 'currency' => $input['currency'] ?? 'USD',
                ...(! empty($input['start_date']) ? ['productFiltering' => ['dateRange' => ['from' => $input['start_date'], 'to' => $input['end_date'] ?? $input['start_date']]]] : []),
            ])->throw()->json();
            if (! is_array(data_get($response, 'products.results'))) {
                throw new \RuntimeException('Invalid supplier response');
            }

            return collect($response['products']['results'])->map(fn ($p) => ['id' => $p['productCode'], 'title' => $p['title'], 'description' => $p['description'] ?? '', 'price' => data_get($p, 'pricing.summary.fromPrice'), 'currency' => data_get($p, 'pricing.currency'), 'price_basis' => 'from', 'image' => self::safeImage(data_get($p, 'images.0.variants.0.url')), 'url' => self::safeUrl($p['productUrl'] ?? null, ['viator.com']), 'rating' => data_get($p, 'reviews.combinedAverageRating')])->all();
        }

        if ($provider->code === 'booking') {
            $locale = $locale === 'en' ? 'en-gb' : $locale;
            $host = $provider->environment === 'live' ? 'https://demandapi.booking.com/3.1' : 'https://demandapi-sandbox.booking.com/3.1';
            $client = $http->withToken($key)->withHeaders(['X-Affiliate-Id' => $provider->credentials['affiliate_id']]);
            $response = $client->post($host.'/accommodations/search', [
                'booker' => ['country' => strtolower($input['booker_country']), 'platform' => $input['platform'] ?? 'desktop'],
                'checkin' => $input['start_date'], 'checkout' => $input['end_date'], 'coordinates' => ['latitude' => (float) $lat, 'longitude' => (float) $lon, 'radius' => 10],
                'guests' => ['number_of_adults' => $adults, 'number_of_rooms' => (int) ($input['rooms'] ?? 1), 'children' => $children],
                'currency' => $input['currency'] ?? 'USD', 'rows' => 20, 'extras' => ['extra_charges', 'products'],
            ])->throw()->json();
            if (! is_array($response['data'] ?? null)) {
                throw new \RuntimeException('Invalid supplier response');
            }
            if (! $response['data']) {
                return [];
            }
            $details = $client->post($host.'/accommodations/details', ['accommodations' => array_column($response['data'], 'id'), 'languages' => [$locale], 'extras' => ['photos']])->throw()->json('data');
            if (! is_array($details)) {
                throw new \RuntimeException('Invalid supplier response');
            }
            $byId = collect($details)->keyBy('id');

            return collect($response['data'])->map(function ($p) use ($byId, $locale) {
                $detail = $byId->get($p['id'], []);

                return ['id' => (string) $p['id'], 'title' => data_get($detail, 'name.'.$locale) ?? data_get($detail, 'name.fallback') ?? data_get($detail, 'name.en') ?? 'Accommodation '.$p['id'], 'description' => data_get($detail, 'location.address.'.$locale) ?? data_get($detail, 'location.address.fallback', ''), 'price' => data_get($p, 'price.total'), 'currency' => $p['currency'] ?? null, 'price_basis' => 'total', 'image' => self::safeImage(data_get($detail, 'photos.0.url.standard')), 'url' => self::safeUrl($p['url'] ?? null, ['booking.com'])];
            })->all();
        }

        if ($provider->code === 'hotelbeds') {
            $host = $provider->environment === 'live' ? 'https://api.hotelbeds.com' : 'https://api.test.hotelbeds.com';
            $signature = hash('sha256', $key.$provider->credentials['secret'].time());
            $childCount = count(array_filter($children, fn ($age) => $age >= 3 && $age < 12));
            $infants = count(array_filter($children, fn ($age) => $age < 3));
            $adultCount = $adults + count(array_filter($children, fn ($age) => $age >= 12));
            $route = 'IATA/'.rawurlencode($input['origin']).'/to/GPS/'.rawurlencode(sprintf('%.6f,%.6f', $lat, $lon));
            $date = $input['start_date'].'T'.($input['departure_time'] ?? '12:00').':00';
            $response = $http->withHeaders(['Api-key' => $key, 'X-Signature' => $signature])->get($host."/transfer-api/1.0/availability/$locale/from/$route/$date/$adultCount/$childCount/$infants")->throw()->json();
            if (! is_array($response['services'] ?? null)) {
                throw new \RuntimeException('Invalid supplier response');
            }

            return collect($response['services'])->take(20)->map(fn ($p) => ['id' => (string) ($p['rateKey'] ?? $p['id']), 'title' => ($p['vehicle']['name'] ?? 'Transfer').' · '.($p['category']['name'] ?? ''), 'description' => ($p['transferType'] ?? '').' · '.data_get($p, 'pickupInformation.pickup.description', ''), 'price' => data_get($p, 'price.totalAmount'), 'currency' => data_get($p, 'price.currencyId'), 'price_basis' => 'total', 'image' => null, 'url' => null])->all();
        }
        throw new \RuntimeException('Unsupported supplier');
    }

    public static function safeUrl(?string $url, array $domains): ?string
    {
        if (! $url || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PASS)) {
            return null;
        }
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $url;
            }
        }

        return null;
    }

    private static function safeImage(?string $url): ?string
    {
        return self::safeUrl($url, ['media.tacdn.com', 'cache.marriott.com', 'bstatic.com', 'viator.com']);
    }
}
