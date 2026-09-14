<?php

namespace App\Services\Travel;

use App\Models\TravelProvider;

class ProviderIntegration
{
    public const CODES = ['anriva', 'maratuk', 'world_voyage', 'travelone'];

    public const CREDENTIALS = [
        'bearer' => ['api_key'], 'api_key' => ['api_key'],
        'basic' => ['username', 'password'], 'tourvisio' => ['agency', 'username', 'password'],
    ];

    public static function profile(TravelProvider $provider, string $environment): array
    {
        return $provider->integration_profiles[$environment] ?? [
            'version' => 0, 'base_url' => '', 'documentation_url' => $provider->code === 'travelone' ? 'https://docs.santsg.com/tourvisio/' : '',
            'auth_type' => $provider->code === 'travelone' ? 'tourvisio' : 'bearer', 'api_key_header' => 'X-API-Key',
            'access_confirmed' => false, 'search_allowed' => false, 'booking_allowed' => false,
            'credentials' => [], 'status' => 'not_configured', 'checked_at' => null, 'last_error' => null,
        ];
    }

    public static function missing(array $profile): array
    {
        $missing = [];
        if (empty($profile['base_url'])) {
            $missing[] = 'API սերվերի հասցե';
        }
        if (empty($profile['documentation_url'])) {
            $missing[] = 'API փաստաթղթերի հղում';
        }
        if (empty($profile['access_confirmed'])) {
            $missing[] = 'API հասանելիության հաստատում';
        }
        foreach (self::CREDENTIALS[$profile['auth_type']] as $field) {
            if (empty($profile['credentials'][$field])) {
                $missing[] = ['api_key' => 'API բանալի', 'agency' => 'Agency կոդ', 'username' => 'Օգտանուն', 'password' => 'Գաղտնաբառ'][$field];
            }
        }

        return $missing;
    }

    public static function safe(TravelProvider $provider): array
    {
        $profiles = [];
        foreach (['sandbox', 'live'] as $environment) {
            $profile = self::profile($provider, $environment);
            $profiles[$environment] = [
                ...array_diff_key($profile, array_flip(['credentials'])),
                'credential_fields_set' => array_keys(array_filter($profile['credentials'] ?? [])),
                'missing' => self::missing($profile),
            ];
        }

        return ['adapter' => $provider->code === 'travelone' ? 'tourvisio' : 'pending',
            'scope' => 'admin_search', 'public_search_enabled' => false, 'booking_enabled' => false, 'profiles' => $profiles];
    }

    public static function fingerprint(array $profile): string
    {
        return hash('sha256', json_encode(array_intersect_key($profile, array_flip(['version', 'base_url', 'auth_type', 'credentials', 'access_confirmed', 'search_allowed']))));
    }
}
