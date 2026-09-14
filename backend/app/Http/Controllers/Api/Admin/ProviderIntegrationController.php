<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelProvider;
use App\Services\Travel\ProviderIntegration;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\SupplierEndpoint;
use App\Services\Travel\TourVisioClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProviderIntegrationController extends Controller
{
    private function guard(string $code, string $environment): void
    {
        abort_unless(in_array($code, ProviderIntegration::CODES) && in_array($environment, ['sandbox', 'live']), 404);
    }

    public function save(Request $request, string $code, string $environment, SupplierEndpoint $endpoint)
    {
        $this->guard($code, $environment);
        $input = $request->validate([
            'version' => ['required', 'integer', 'min:0'], 'base_url' => ['present', 'nullable', 'string', 'max:500'],
            'documentation_url' => ['present', 'nullable', 'url:https', 'max:500'],
            'auth_type' => ['required', Rule::in($code === 'travelone' ? ['tourvisio'] : ['bearer', 'api_key', 'basic'])],
            'api_key_header' => ['required', 'regex:/^X-[A-Za-z0-9-]{1,60}$/D'],
            'access_confirmed' => ['required', 'boolean'], 'search_allowed' => ['required', 'boolean'], 'booking_allowed' => ['required', 'boolean'],
            'credentials' => ['present', 'array:api_key,agency,username,password'], 'credentials.*' => ['nullable', 'string', 'max:2000'],
            'clear_credentials' => ['required', 'boolean'],
        ]);
        $input['base_url'] = filled($input['base_url']) ? $endpoint->validate($input['base_url']) : '';
        if (filled($input['documentation_url'])) {
            $endpoint->validate($input['documentation_url'], documentation: true);
        }
        $lock = Cache::lock('provider-profile-save:'.$code, 10);
        abort_unless($lock->get(), 409, 'Պահպանումն արդեն ընթացքի մեջ է։ Կրկին փորձեք։');
        try {
            return DB::transaction(function () use ($code, $environment, $input) {
                $provider = TravelProvider::firstOrCreate(['code' => $code], ['enabled' => false, 'environment' => 'sandbox', 'status' => 'not_configured']);
                $provider = TravelProvider::lockForUpdate()->findOrFail($provider->id);
                $profiles = $provider->integration_profiles ?? [];
                $old = ProviderIntegration::profile($provider, $environment);
                abort_unless((int) $input['version'] === $old['version'], 409, 'Կարգավորումներն արդեն փոխվել են։ Թարմացրեք էջը։');
                // A changed destination or authentication scheme must not inherit old secrets.
                $reset = $input['clear_credentials'] || $input['base_url'] !== $old['base_url'] || $input['auth_type'] !== $old['auth_type'];
                $credentials = $reset ? [] : $old['credentials'];
                foreach ($input['credentials'] as $field => $value) {
                    abort_unless(in_array($field, ProviderIntegration::CREDENTIALS[$input['auth_type']]), 422, 'Մուտքի դաշտը չի համապատասխանում ընտրված եղանակին։');
                    if (filled($value) && ! $input['clear_credentials']) {
                        $credentials[$field] = $value;
                    }
                }
                $profile = array_diff_key($input, array_flip(['clear_credentials']));
                $profile['credentials'] = $credentials;
                $profile['version'] = $old['version'] + 1;
                $profile['status'] = ProviderIntegration::missing($profile) ? 'not_configured' : ($code === 'travelone' ? 'not_tested' : 'adapter_pending');
                $profile['checked_at'] = null;
                $profile['last_error'] = null;
                $profiles[$environment] = $profile;
                $provider->update(['integration_profiles' => $profiles]);

                return ProviderRegistry::safe($provider);
            });
        } finally {
            $lock->release();
        }
    }

    public function inspect(string $code, string $environment)
    {
        $this->guard($code, $environment);
        $provider = TravelProvider::firstOrNew(['code' => $code]);
        $profile = ProviderIntegration::profile($provider, $environment);

        return ['missing' => ProviderIntegration::missing($profile), 'adapter_ready' => $code === 'travelone', 'external_request_sent' => false, 'provider' => ProviderRegistry::safe($provider)];
    }

    public function test(string $code, string $environment, TourVisioClient $client)
    {
        return $this->run($code, $environment, function ($profile) use ($client) {
            $client->login($profile);

            return ['authenticated' => true, 'kind' => 'authentication', 'booking_enabled' => false];
        });
    }

    public function dictionary(Request $request, string $code, string $environment, string $kind, TourVisioClient $client)
    {
        abort_unless(in_array($kind, ['departures', 'arrivals']), 404);
        $input = $request->validate(['departure_id' => [$kind === 'arrivals' ? 'required' : 'nullable', 'string', 'max:80'], 'departure_type' => [$kind === 'arrivals' ? 'required' : 'nullable', 'integer', 'between:1,4']]);

        return $this->run($code, $environment, fn ($profile) => ['data' => $client->dictionary($profile, $kind, $input)], true);
    }

    public function search(Request $request, string $code, string $environment, TourVisioClient $client)
    {
        $input = $request->validate([
            'departure_id' => ['required', 'string', 'max:80'], 'departure_type' => ['required', 'integer', 'between:1,4'],
            'arrival_id' => ['required', 'string', 'max:80'], 'arrival_type' => ['required', 'integer', 'between:1,4'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()],
            'nights' => ['required', 'integer', 'between:1,28'], 'adults' => ['required', 'integer', 'between:1,6'],
            'children' => ['present', 'array', 'max:3'], 'children.*' => ['integer', 'between:0,17'],
            'currency' => ['required', 'in:AMD,USD,EUR,RUB,GBP,AED'], 'nationality' => ['required', 'regex:/^[A-Z]{2}$/D'],
        ]);

        return $this->run($code, $environment, fn ($profile) => $client->search($profile, $input), true);
    }

    private function run(string $code, string $environment, callable $operation, bool $search = false)
    {
        $this->guard($code, $environment);
        abort_unless($code === 'travelone', 422, 'Այս մատակարարի ադապտերը սպասում է API փաստաթղթերին։ Արտաքին հարցում չի կատարվել։');
        $provider = TravelProvider::where('code', $code)->firstOrFail();
        $profile = ProviderIntegration::profile($provider, $environment);
        abort_if(ProviderIntegration::missing($profile), 422, 'Լրացրեք API կարգավորումները և հաստատեք մատակարարի հասանելիությունը։');
        abort_if($search && ! $profile['search_allowed'], 422, 'Փաթեթների որոնման թույլտվությունը հաստատված չէ։');
        $lock = Cache::lock('provider-operation:'.$code.':'.$environment, 30);
        abort_unless($lock->get(), 409, 'Այս միջավայրի հարցումն արդեն ընթացքի մեջ է։');
        try {
            try {
                $result = $operation($profile);
                $message = null;
            } catch (\Throwable) {
                $message = 'API հարցումը չհաջողվեց։ Ստուգեք հասցեն, տվյալները և մատակարարի թույլտվությունները։';
                $result = [];
            }
            $fresh = DB::transaction(function () use ($provider, $environment, $profile, $message) {
                $fresh = TravelProvider::lockForUpdate()->findOrFail($provider->id);
                $profiles = $fresh->integration_profiles;
                abort_unless(hash_equals(ProviderIntegration::fingerprint($profile), ProviderIntegration::fingerprint($profiles[$environment])), 409, 'Հարցման ընթացքում կարգավորումները փոխվել են։ Կրկին ստուգեք։');
                $profiles[$environment]['status'] = $message ? 'error' : 'authenticated';
                $profiles[$environment]['checked_at'] = now()->toIso8601String();
                $profiles[$environment]['last_error'] = $message;
                $fresh->update(['integration_profiles' => $profiles]);

                return $fresh;
            });

            return response()->json([...$result, 'message' => $message, 'provider' => ProviderRegistry::safe($fresh)], $message ? 502 : 200);
        } finally {
            $lock->release();
        }
    }
}
