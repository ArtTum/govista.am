<?php

namespace App\Services\Travel;

use Illuminate\Support\Facades\Http;

/** TourVisio is a separate protocol from Tourvisor. Read-only admin operations. */
class TourVisioClient
{
    public function __construct(private SupplierEndpoint $endpoint) {}

    private function post(array $profile, string $method, array $body, ?string $token = null): array
    {
        $base = $this->endpoint->validate($profile['base_url']);
        $request = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10)
            ->withOptions($this->endpoint->options($base))->withoutRedirecting();
        if ($token) {
            $request = $request->withToken($token);
        }
        $response = $request->post($base.'/api/'.$method, $body)->throw();
        if (! $response->successful()) {
            throw new \RuntimeException('Unexpected supplier HTTP status');
        }
        if (strlen($response->body()) > 4_000_000) {
            throw new \RuntimeException('Supplier response too large');
        }
        $json = $response->json();
        if (data_get($json, 'header.success') !== true || ! is_array($json['body'] ?? null)) {
            throw new \RuntimeException('Supplier rejected request');
        }

        return $json['body'];
    }

    public function login(array $profile): string
    {
        $credentials = $profile['credentials'];
        $body = $this->post($profile, 'authenticationservice/login', ['Agency' => $credentials['agency'], 'User' => $credentials['username'], 'Password' => $credentials['password']]);
        $token = $body['token'] ?? null;
        if (! is_string($token) || ! preg_match('/^[a-zA-Z0-9._~+\/=\-]{8,8192}$/D', $token)) {
            throw new \RuntimeException('Missing supplier token');
        }

        // Ephemeral token: never returned to the browser or persisted in plaintext.
        return $token;
    }

    public function dictionary(array $profile, string $kind, array $input): array
    {
        $payload = ['ProductType' => 1, 'culture' => 'en-US'];
        if ($kind === 'arrivals') {
            $payload['DepartureLocations'] = [['Id' => $input['departure_id'], 'Type' => (int) $input['departure_type']]];
        }
        $body = $this->post($profile, 'productservice/'.($kind === 'departures' ? 'getdepartures' : 'getarrivals'), $payload, $this->login($profile));
        if (! is_array($body['locations'] ?? null)) {
            throw new \RuntimeException('Invalid supplier locations');
        }

        return collect($body['locations'])->take(2000)->filter(fn ($row) => is_array($row) && is_scalar($row['id'] ?? null) && is_string($row['name'] ?? null) && in_array($row['type'] ?? null, [1, 2, 3, 4]))
            ->map(fn ($row) => ['id' => mb_substr((string) $row['id'], 0, 80), 'type' => (int) $row['type'], 'name' => mb_substr(strip_tags($row['name']), 0, 190)])->values()->all();
    }

    public function search(array $profile, array $input): array
    {
        foreach (['departure_type', 'arrival_type', 'nights', 'adults'] as $field) {
            $input[$field] = (int) $input[$field];
        }
        $input['children'] = array_map('intval', array_values($input['children']));
        $body = $this->post($profile, 'productservice/pricesearch', [
            'ProductType' => 1,
            'DepartureLocations' => [['Id' => $input['departure_id'], 'Type' => $input['departure_type']]],
            'ArrivalLocations' => [['Id' => $input['arrival_id'], 'Type' => $input['arrival_type']]],
            'IncludeSubLocations' => true, 'CheckIn' => $input['check_in'], 'Night' => $input['nights'],
            'RoomCriteria' => [['Adult' => $input['adults'], 'ChildAges' => $input['children']]],
            'CheckAllotment' => true, 'CheckStopSale' => true,
            'Nationality' => $input['nationality'], 'currency' => $input['currency'], 'culture' => 'en-US',
        ], $this->login($profile));
        if (! is_array($body['hotels'] ?? null)) {
            throw new \RuntimeException('Invalid supplier search');
        }
        $offers = [];
        foreach (array_slice($body['hotels'], 0, 30) as $hotel) {
            if (! is_array($hotel) || ! is_array($hotel['offers'] ?? null)) {
                continue;
            }
            foreach (array_slice($hotel['offers'], 0, 5) as $offer) {
                if (! is_array($offer) || ! is_string($offer['offerId'] ?? null) || empty($offer['offerId']) || ! is_array($offer['rooms'] ?? null) || count($offer['rooms']) !== 1 || ! is_array($offer['rooms'][0] ?? null) || ! is_string($offer['checkIn'] ?? null)) {
                    continue;
                }
                if ((int) ($offer['night'] ?? 0) !== (int) $input['nights'] || substr($offer['checkIn'] ?? '', 0, 10) !== $input['check_in']) {
                    continue;
                }
                $currency = data_get($offer, 'price.currency');
                $amount = data_get($offer, 'price.amount');
                $validPrice = in_array($currency, ['AMD', 'USD', 'EUR', 'RUB', 'GBP', 'AED']) && is_numeric($amount) && $amount > 0 && $amount <= 999999999.99;
                $room = $offer['rooms'][0];
                $offers[] = ['offer_id' => mb_substr($offer['offerId'], 0, 500), 'hotel' => $this->text($hotel['name'] ?? null),
                    'stars' => max(0, min(5, (float) ($hotel['stars'] ?? 0))), 'check_in' => $input['check_in'], 'nights' => $input['nights'],
                    'room' => $this->text($room['roomName'] ?? null), 'meal' => $this->text($room['boardName'] ?? null),
                    'price' => $validPrice ? (string) $amount : null, 'currency' => $validPrice ? $currency : null,
                    'available' => ($offer['isAvailable'] ?? false) === true, 'price_basis' => 'party_total',
                    'adults' => $input['adults'], 'children' => $input['children'],
                ];
            }
        }

        return ['data' => $offers, 'count' => count($offers), 'limited' => count($body['hotels']) > 30 || collect($body['hotels'])->contains(fn ($hotel) => is_array($hotel['offers'] ?? null) && count($hotel['offers']) > 5), 'booking_enabled' => false];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? mb_substr(strip_tags($value), 0, 190) : '';
    }
}
