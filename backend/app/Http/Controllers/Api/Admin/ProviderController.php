<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelProvider;
use App\Services\Travel\PackageCatalog;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\ProviderSearch;
use App\Services\Travel\TourvisorSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProviderController extends Controller
{
    public function index()
    {
        return array_map(function ($code) {
            $provider = TravelProvider::firstOrNew(['code' => $code], ['enabled' => false, 'environment' => 'sandbox', 'status' => 'not_configured']);

            return ProviderRegistry::safe($provider);
        }, array_keys(ProviderRegistry::DEFINITIONS));
    }

    public function update(Request $request, string $code)
    {
        $definition = ProviderRegistry::definition($code);
        $rules = ['enabled' => ['required', 'boolean'], 'environment' => ['required', 'in:sandbox,live'], 'credentials' => $definition['fields'] ? ['sometimes', 'array:'.implode(',', $definition['fields'])] : ['prohibited'], 'clear_credentials' => ['sometimes', 'boolean']];
        if (in_array($code, PackageCatalog::PROVIDERS)) {
            $rules += [
                'settings' => ['required', 'array:partnership_status,contract_reference,content_rights_confirmed,evn_confirmed,contact_name,contact_email,notes,departure_id,destination_ids'],
                'settings.partnership_status' => ['required', 'in:not_started,requested,approved,paused'],
                'settings.contract_reference' => ['nullable', 'string', 'max:190'],
                'settings.content_rights_confirmed' => ['required', 'boolean'], 'settings.evn_confirmed' => ['required', 'boolean'],
                'settings.contact_name' => ['nullable', 'string', 'max:190'], 'settings.contact_email' => ['nullable', 'email', 'max:190'],
                'settings.notes' => ['nullable', 'string', 'max:4000'], 'settings.departure_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
                'settings.destination_ids' => ['sometimes', 'array:'.implode(',', array_keys(PackageCatalog::DESTINATIONS))],
                'settings.destination_ids.*' => ['array:country_id,region_id'],
                'settings.destination_ids.*.country_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
                'settings.destination_ids.*.region_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            ];
        }
        foreach ($definition['fields'] as $field) {
            $rules['credentials.'.$field] = ['nullable', 'string', 'max:2000'];
        }
        $data = $request->validate($rules);
        $provider = TravelProvider::firstOrNew(['code' => $code]);
        $secrets = $data['clear_credentials'] ?? false ? [] : ($provider->credentials ?? []);
        foreach ($data['credentials'] ?? [] as $key => $value) {
            if (filled($value)) {
                $secrets[$key] = $value;
            }
        }
        if ($code === 'duffel' && isset($secrets['api_key']) && ! str_starts_with($secrets['api_key'], $data['environment'] === 'live' ? 'duffel_live_' : 'duffel_test_')) {
            throw ValidationException::withMessages(['credentials.api_key' => ['Duffel բանալին չի համապատասխանում ընտրված միջավայրին։']]);
        }
        $provider->fill(['credentials' => $secrets, 'enabled' => $data['enabled'], 'environment' => $data['environment']]);
        if (isset($data['settings'])) {
            $provider->settings = $data['settings'];
        }
        if ($definition['mode'] === 'manual' && $provider->enabled) {
            throw ValidationException::withMessages(['enabled' => ['Այս մատակարարի API կապը դեռ իրականացված չէ։ Առաջարկները կառավարեք փաթեթների բաժնում։']]);
        }
        if ($code === 'tourvisor' && $provider->enabled && $provider->environment === 'live' && (! PackageCatalog::approved($provider) || ! data_get($provider->settings, 'evn_confirmed') || ! data_get($provider->settings, 'departure_id'))) {
            throw ValidationException::withMessages(['settings' => ['Հաստատեք համագործակցությունը, հրապարակման իրավունքը և EVN մեկնման կոդը։']]);
        }
        if ($provider->enabled && ! ProviderRegistry::ready($provider)) {
            throw ValidationException::withMessages(['credentials' => ['Լրացրեք բոլոր պահանջվող բանալիները։']]);
        }
        if ($provider->isDirty(['credentials', 'environment', 'settings'])) {
            $provider->fill(['status' => ProviderRegistry::ready($provider) ? 'not_tested' : 'not_configured', 'last_error' => null, 'last_checked_at' => null, 'last_success_at' => null]);
        }
        $provider->save();

        return ProviderRegistry::safe($provider);
    }

    public function test(string $code, ProviderSearch $search)
    {
        $definition = ProviderRegistry::definition($code);
        abort_if($definition['mode'] === 'manual', 422, 'Այս մատակարարի համար արտաքին API ստուգում հասանելի չէ։');
        $provider = TravelProvider::where('code', $code)->firstOrFail();
        abort_unless(ProviderRegistry::ready($provider), 422, 'Սկզբում պահպանեք API բանալիները։');
        $input = ['locale' => 'en', 'origin' => 'LHR', 'destination' => $code === 'duffel' ? 'JFK' : 'London', 'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonth()->addDays(3)->toDateString(), 'adults' => 2, 'rooms' => 1, 'latitude' => 51.5074, 'longitude' => -0.1278, 'booker_country' => 'am', 'currency' => 'USD'];
        try {
            if ($code === 'tourvisor') {
                $departures = app(TourvisorSearch::class)->dictionary($provider, 'departures');
                $provider->update(['status' => 'connected', 'last_checked_at' => now(), 'last_success_at' => now(), 'last_error' => null]);

                return ['provider' => ProviderRegistry::safe($provider), 'result_count' => count($departures), 'results' => array_map(fn ($item) => ['id' => $item['id'], 'title' => $item['name']], array_slice($departures, 0, 3)), 'kind' => 'dictionary'];
            }
            $results = $search->search($provider, $input);
            $provider->update(['status' => 'connected', 'last_checked_at' => now(), 'last_success_at' => now(), 'last_error' => null]);

            return ['provider' => ProviderRegistry::safe($provider), 'result_count' => count($results), 'results' => array_slice($results, 0, 3)];
        } catch (\Throwable $error) {
            // Never log exception bodies or URLs: providers may echo API keys.
            $provider->update(['status' => 'error', 'last_checked_at' => now(), 'last_error' => 'Կապը չհաջողվեց։ Ստուգեք բանալիները, միջավայրը և մատակարարի հասանելիությունը։']);

            return response()->json(['message' => $provider->last_error, 'provider' => ProviderRegistry::safe($provider)], 502);
        }
    }

    public function dictionary(Request $request, string $code, string $kind)
    {
        abort_unless($code === 'tourvisor' && in_array($kind, ['departures', 'countries', 'regions']), 404);
        $input = $request->validate(['departure_id' => ['nullable', 'integer', 'min:1'], 'country_id' => ['nullable', 'integer', 'min:1']]);
        $provider = TravelProvider::where('code', $code)->firstOrFail();
        abort_unless(ProviderRegistry::ready($provider), 422, 'Պահպանեք API բանալին։');
        try {
            return ['data' => app(TourvisorSearch::class)->dictionary($provider, $kind, $input)];
        } catch (\Throwable) {
            return response()->json(['message' => 'Տեղեկատուն չբեռնվեց։ Ստուգեք կապի հասանելիությունը։'], 502);
        }
    }
}
