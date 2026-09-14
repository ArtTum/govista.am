<?php

namespace App\Services\Travel;

use App\Models\TravelProvider;
use Illuminate\Support\Facades\Http;

/** Documented Tourvisor search endpoints only; never creates a booking. */
class TourvisorSearch
{
    private function get(TravelProvider $provider, string $path, array $query = []): array
    {
        $result = Http::acceptJson()->withToken($provider->credentials['api_key'])->connectTimeout(3)->timeout(8)->withoutRedirecting()
            ->get('https://api.tourvisor.ru/search/api/v1/'.$path, $query)->throw()->json();
        if (! is_array($result)) {
            throw new \RuntimeException('Invalid supplier response');
        }

        return $result;
    }

    public function fingerprint(TravelProvider $provider): string
    {
        return hash('sha256', json_encode([$provider->credentials, $provider->settings, $provider->environment]));
    }

    public function dictionary(TravelProvider $provider, string $kind, array $input = []): array
    {
        if (! in_array($kind, ['departures', 'countries', 'regions'])) {
            throw new \InvalidArgumentException('Unknown dictionary');
        }
        $query = match ($kind) {
            'departures' => ['departureCountryId' => 99],
            'countries' => array_filter(['departureId' => $input['departure_id'] ?? null]),
            'regions' => array_filter(['countryId' => $input['country_id'] ?? null]),
        };
        $rows = $this->get($provider, $kind, $query);
        if (! array_is_list($rows)) {
            throw new \RuntimeException('Invalid dictionary');
        }

        return collect($rows)->take(1000)->filter(fn ($row) => is_numeric($row['id'] ?? null) && is_string($row['name'] ?? null))
            ->map(fn ($row) => ['id' => (int) $row['id'], 'name' => mb_substr(strip_tags($row['name']), 0, 190)])->values()->all();
    }

    public function start(TravelProvider $provider, array $input): string
    {
        $mapping = data_get($provider->settings, 'destination_ids.'.$input['destination_code']);
        if (empty($mapping['country_id']) || empty($mapping['region_id']) || empty($provider->settings['departure_id'])) {
            throw new \InvalidArgumentException('Destination mapping is incomplete');
        }
        $query = [
            'departureId' => (int) $provider->settings['departure_id'], 'countryId' => (int) $mapping['country_id'],
            'regionIds' => (string) $mapping['region_id'], 'dateFrom' => $input['date_from'], 'dateTo' => $input['date_to'],
            'nightsFrom' => $input['nights_min'], 'nightsTo' => $input['nights_max'], 'adults' => $input['adults'],
            'currency' => 'CU', 'onlyCharter' => 'false', 'onlyDirect' => $input['direct_only'] ? 'true' : 'false',
        ];
        if (! empty($input['children'])) {
            $query['childs'] = implode(',', $input['children']);
        }
        if (! empty($input['stars'])) {
            $query['hotelCategory'] = $input['stars'];
        }
        $response = $this->get($provider, 'tours/search', $query);
        if (! preg_match('/^[0-9]+$/', (string) ($response['searchId'] ?? '')) || (int) $response['searchId'] < 1) {
            throw new \RuntimeException('Missing supplier search ID');
        }

        return (string) $response['searchId'];
    }

    public function results(TravelProvider $provider, string $searchId, array $input): array
    {
        if (! preg_match('/^[0-9]+$/', $searchId)) {
            throw new \InvalidArgumentException('Invalid search ID');
        }
        $status = $this->get($provider, 'tours/search/'.$searchId.'/status', ['operatorStatus' => 'false']);
        $hotels = $this->get($provider, 'tours/search/'.$searchId, ['limit' => 20]);
        if (! is_numeric($status['progress'] ?? null) || ! array_is_list($hotels)) {
            throw new \RuntimeException('Invalid search result');
        }
        $offers = [];
        foreach (array_slice($hotels, 0, 20) as $hotel) {
            if (! is_array($hotel) || ! is_array($hotel['tours'] ?? null)) {
                continue;
            }
            foreach (array_slice($hotel['tours'] ?? [], 0, 5) as $tour) {
                if (! is_array($tour) || ! is_string($tour['id'] ?? null) || $tour['id'] === '' || strlen($tour['id']) > 120 || ! is_numeric($tour['price'] ?? null) || $tour['price'] <= 0 || $tour['price'] > 999999999.99 || ! is_string($tour['date'] ?? null)) {
                    continue;
                }
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $tour['date']);
                if (! $date || $date->format('Y-m-d') !== $tour['date'] || $tour['date'] < $input['date_from'] || $tour['date'] > $input['date_to']) {
                    continue;
                }
                if (! is_numeric($tour['nights'] ?? null) || $tour['nights'] < $input['nights_min'] || $tour['nights'] > $input['nights_max'] || (int) ($tour['adults'] ?? 0) !== (int) $input['adults'] || (int) ($tour['childs'] ?? -1) !== count($input['children'])) {
                    continue;
                }
                $currency = strtoupper($tour['currency'] ?? $hotel['currency'] ?? '');
                // CU is a supplier accounting unit, never relabel it USD or EUR.
                $knownCurrency = in_array($currency, ['AMD', 'USD', 'EUR', 'RUB', 'GBP', 'AED']);
                $offers[] = [
                    'id' => $tour['id'], 'provider' => 'tourvisor', 'source' => 'Tourvisor',
                    'operator' => mb_substr(strip_tags(data_get($tour, 'operator.name', '')), 0, 190),
                    'title' => mb_substr(strip_tags($hotel['name'] ?? 'Hotel'), 0, 190),
                    'hotel_name' => mb_substr(strip_tags($hotel['name'] ?? ''), 0, 190),
                    'description' => mb_substr(strip_tags(($tour['roomType'] ?? '').' · '.($tour['placement'] ?? '')), 0, 500),
                    'destination_code' => $input['destination_code'], 'destination' => PackageCatalog::DESTINATIONS[$input['destination_code']]['names'][$input['locale']],
                    'origin' => 'EVN', 'departure_date' => $tour['date'], 'nights' => (int) ($tour['nights'] ?? 0),
                    'adults' => (int) ($tour['adults'] ?? $input['adults']), 'children' => $input['children'],
                    'meal_plan' => mb_substr(strip_tags(data_get($tour, 'meal.name', '')), 0, 30),
                    'price' => $knownCurrency ? (string) $tour['price'] : null, 'currency' => $knownCurrency ? $currency : null,
                    'price_basis' => 'indicative_total', 'fuel_charge' => $knownCurrency && is_numeric($tour['fuelCharge'] ?? null) && $tour['fuelCharge'] >= 0 ? $tour['fuelCharge'] : null,
                    'stars' => max(0, min(5, (int) ($hotel['category'] ?? 0))), 'image' => null,
                    'inclusions' => [], 'terms' => '', 'exclusions' => '', 'availability' => 'on_request', 'live' => true,
                ];
            }
        }

        return ['data' => collect($offers)->unique('id')->values()->all(), 'progress' => max(0, min(100, (int) $status['progress'])), 'complete' => (int) $status['progress'] >= 100];
    }
}
