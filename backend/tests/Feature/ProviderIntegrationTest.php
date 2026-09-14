<?php

namespace Tests\Feature;

use App\Models\TravelProvider;
use App\Models\User;
use App\Services\Travel\ProviderIntegration;
use App\Services\Travel\SupplierEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->partialMock(SupplierEndpoint::class, fn ($mock) => $mock->shouldReceive('resolve')->andReturn(['93.184.215.14']));
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::firstOrCreate(['email' => 'integration-admin@example.test'], ['name' => 'Admin', 'role' => 'admin', 'password' => 'ExamplePass42!']));
    }

    private function url(string $code = 'travelone', string $env = 'sandbox'): string
    {
        return '/api/admin/providers/'.$code.'/integration/'.$env;
    }

    private function config(string $code = 'travelone', array $overrides = []): array
    {
        return array_replace([
            'version' => 0, 'base_url' => 'https://supplier.example.com/v2', 'documentation_url' => 'https://docs.santsg.com/tourvisio/',
            'auth_type' => $code === 'travelone' ? 'tourvisio' : 'bearer', 'api_key_header' => 'X-API-Key',
            'access_confirmed' => true, 'search_allowed' => true, 'booking_allowed' => false,
            'credentials' => $code === 'travelone' ? ['agency' => 'secret-agency', 'username' => 'secret-user', 'password' => 'secret-password'] : ['api_key' => 'secret-api-key'],
            'clear_credentials' => false,
        ], $overrides);
    }

    private function install(string $code = 'travelone', string $env = 'sandbox', array $overrides = []): void
    {
        $this->admin();
        $this->putJson($this->url($code, $env), $this->config($code, $overrides))->assertOk();
    }

    private function searchInput(): array
    {
        return ['departure_id' => 'EVN-CITY', 'departure_type' => 2, 'arrival_id' => 'SSH-CITY', 'arrival_type' => 2, 'check_in' => today()->addDays(14)->toDateString(), 'nights' => 7, 'adults' => 2, 'children' => [7], 'currency' => 'USD', 'nationality' => 'AM'];
    }

    private function success(array $body): array
    {
        return ['header' => ['success' => true], 'body' => $body];
    }

    public function test_only_admins_can_manage_profiles_and_call_supplier_operations(): void
    {
        $this->putJson($this->url(), $this->config())->assertUnauthorized();
        $this->postJson($this->url().'/test')->assertUnauthorized();
        Sanctum::actingAs(User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'role' => 'customer', 'password' => 'ExamplePass42!']));
        $this->putJson($this->url(), $this->config())->assertForbidden();
        foreach (['inspect', 'test', 'dictionary/departures', 'search'] as $action) {
            $this->postJson($this->url().'/'.$action, $this->searchInput())->assertForbidden();
        }
        $this->admin();
        $this->putJson($this->url('unknown'), $this->config())->assertNotFound();
        $this->putJson($this->url('tourvisor'), $this->config())->assertNotFound();
        $this->putJson($this->url('travelone', 'production'), $this->config())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_profiles_are_encrypted_write_only_and_never_enable_public_search(): void
    {
        $this->install();
        $this->install('travelone', 'live', ['credentials' => ['agency' => 'live-agency', 'username' => 'live-user', 'password' => 'live-password'], 'booking_allowed' => true]);
        $raw = DB::table('travel_providers')->where('code', 'travelone')->value('integration_profiles');
        $list = $this->getJson('/api/admin/providers')->assertOk()->assertJsonCount(13);
        $list->assertHeader('Cache-Control', 'no-store, private');
        $provider = TravelProvider::where('code', 'travelone')->firstOrFail();
        foreach (['secret-agency', 'secret-user', 'secret-password', 'live-password'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
            $this->assertStringNotContainsString($secret, $list->getContent());
            $this->assertStringNotContainsString($secret, $provider->toJson());
        }
        $this->postJson($this->url().'/inspect')->assertOk()->assertJsonPath('external_request_sent', false)->assertJsonPath('provider.integration.public_search_enabled', false)->assertJsonPath('provider.integration.booking_enabled', false)->assertJsonMissingPath('provider.integration.profiles.sandbox.credentials');
        $this->assertFalse($provider->enabled);
        $public = $this->getJson('/api/v1/packages/options')->assertOk()->assertJsonCount(8, 'providers')->getContent();
        $this->assertStringNotContainsString('integration_profiles', $public);
        $this->assertStringNotContainsString('supplier.example.com', $public);
        Http::assertNothingSent();
    }

    public function test_blank_credentials_preserve_and_clear_is_environment_scoped(): void
    {
        $this->install();
        $this->install('travelone', 'live', ['credentials' => ['agency' => 'live-agency', 'username' => 'live-user', 'password' => 'live-password']]);
        $this->putJson($this->url(), $this->config('travelone', ['version' => '1', 'credentials' => ['password' => '']]))->assertOk()->assertJsonPath('integration.profiles.sandbox.version', 2);
        $provider = TravelProvider::where('code', 'travelone')->firstOrFail();
        $this->assertSame('secret-password', $provider->integration_profiles['sandbox']['credentials']['password']);
        $this->putJson($this->url(), $this->config('travelone', ['version' => 2, 'clear_credentials' => true]))->assertOk()->assertJsonPath('integration.profiles.sandbox.credential_fields_set', [])->assertJsonPath('integration.profiles.sandbox.status', 'not_configured');
        $this->assertSame('live-password', $provider->fresh()->integration_profiles['live']['credentials']['password']);
        $this->postJson($this->url().'/test')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_password_whitespace_is_preserved_exactly(): void
    {
        $this->install('travelone', 'sandbox', ['credentials' => ['agency' => 'agency', 'username' => 'user', 'password' => ' leading and trailing spaces ']]);
        $profile = ProviderIntegration::profile(TravelProvider::firstOrFail(), 'sandbox');
        $this->assertSame(' leading and trailing spaces ', $profile['credentials']['password']);
    }

    public function test_documentation_links_allow_sections_and_query_parameters_without_requests(): void
    {
        $this->install('anriva', 'sandbox', ['documentation_url' => 'https://booking.anrivatour.com/index.php?m=pages&p=102#api']);
        $this->assertSame('https://booking.anrivatour.com/index.php?m=pages&p=102#api', TravelProvider::firstOrFail()->integration_profiles['sandbox']['documentation_url']);
        Http::assertNothingSent();
    }

    public function test_endpoint_changes_drop_secrets_and_stale_or_concurrent_saves_are_rejected(): void
    {
        $this->install();
        $this->putJson($this->url(), $this->config())->assertConflict();
        $lock = Cache::lock('provider-profile-save:travelone', 10);
        $lock->get();
        $this->putJson($this->url(), $this->config('travelone', ['version' => 1]))->assertConflict();
        $lock->release();
        $this->putJson($this->url(), $this->config('travelone', ['version' => 1, 'base_url' => 'https://new.supplier.example.com/v2', 'credentials' => []]))->assertOk()->assertJsonPath('integration.profiles.sandbox.credential_fields_set', []);
        $this->assertSame(2, TravelProvider::where('code', 'travelone')->firstOrFail()->integration_profiles['sandbox']['version']);
    }

    public function test_unverified_adapters_store_configuration_but_never_send_http_requests(): void
    {
        foreach (['anriva', 'maratuk', 'world_voyage'] as $code) {
            $this->travel(61)->seconds();
            $this->install($code);
            $this->postJson($this->url($code).'/inspect')->assertOk()->assertJsonPath('missing', [])->assertJsonPath('adapter_ready', false)->assertJsonPath('provider.integration.profiles.sandbox.status', 'adapter_pending');
            $this->postJson($this->url($code).'/test')->assertUnprocessable();
            $this->postJson($this->url($code).'/search', $this->searchInput())->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_configuration_validation_rejects_unsafe_urls_and_wrong_credentials(): void
    {
        $this->admin();
        foreach (['http://supplier.com', 'https://127.0.0.1', 'https://localhost', 'https://internal.local', 'https://user:password@supplier.com', 'https://supplier.com?key=secret', 'https://supplier.com/a/../b', 'https://supplier.com:8443', 'https://supplier.com/%2e%2e'] as $url) {
            $this->putJson($this->url(), $this->config('travelone', ['base_url' => $url]))->assertUnprocessable();
        }
        $this->putJson($this->url(), $this->config('travelone', ['auth_type' => 'bearer']))->assertUnprocessable();
        $this->putJson($this->url('anriva'), $this->config('anriva', ['api_key_header' => "X-Key\r\nHost: secret"]))->assertUnprocessable();
        $this->putJson($this->url(), $this->config('travelone', ['credentials' => ['api_key' => 'secret']]))->assertUnprocessable();
        $this->assertDatabaseCount('travel_providers', 0);
        Http::assertNothingSent();
    }

    public function test_dns_validation_rejects_local_and_multicast_addresses_and_pins_public_ip(): void
    {
        foreach (['127.0.0.1', '10.1.2.3', '169.254.169.254', '100.64.0.1', '224.0.0.1', '::1', '::ffff:127.0.0.1', 'fd00::1', 'ff02::1', '2002:7f00:1::', '64:ff9b::7f00:1'] as $address) {
            $endpoint = \Mockery::mock(SupplierEndpoint::class)->makePartial();
            $endpoint->shouldReceive('resolve')->andReturn(['93.184.215.14', $address]);
            try {
                $endpoint->options('https://supplier.example.com/v2');
                $this->fail('Accepted private/reserved supplier address '.$address);
            } catch (\RuntimeException $e) {
                $this->assertSame('Supplier address is not public', $e->getMessage());
            }
        }
        $options = app(SupplierEndpoint::class)->options('https://supplier.example.com/v2');
        $this->assertSame(['supplier.example.com:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame('', $options['proxy']);
    }

    public function test_missing_access_or_search_grant_stops_before_network(): void
    {
        $this->install('travelone', 'sandbox', ['access_confirmed' => false]);
        $this->postJson($this->url().'/test')->assertUnprocessable();
        $this->putJson($this->url(), $this->config('travelone', ['version' => 1, 'search_allowed' => false]))->assertOk();
        $this->postJson($this->url().'/dictionary/departures')->assertUnprocessable();
        $this->postJson($this->url().'/search', $this->searchInput())->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_tourvisio_authentication_uses_documented_fields_and_keeps_token_private(): void
    {
        $this->install();
        $this->install('travelone', 'live', ['base_url' => 'https://live.supplier.example.com/v2', 'credentials' => ['agency' => 'live-agency', 'username' => 'live-user', 'password' => 'live-password']]);
        Http::fake(['https://live.supplier.example.com/v2/api/authenticationservice/login' => Http::response($this->success(['token' => 'ephemeral-secret-token']), 200)]);
        $response = $this->postJson($this->url('travelone', 'live').'/test')->assertOk()->assertJsonPath('authenticated', true)->assertJsonPath('provider.integration.profiles.live.status', 'authenticated')->assertJsonPath('provider.integration.profiles.sandbox.status', 'not_tested');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->data() === ['Agency' => 'live-agency', 'User' => 'live-user', 'Password' => 'live-password']);
        Http::assertSentCount(1);
        $this->assertStringNotContainsString('ephemeral-secret-token', $response->getContent());
        $this->assertStringNotContainsString('ephemeral-secret-token', json_encode(TravelProvider::firstOrFail()->integration_profiles));
    }

    public function test_supplier_failures_and_redirects_are_sanitized_and_never_marked_connected(): void
    {
        $this->install();
        Http::fake(['*' => Http::sequence()
            ->push(['header' => ['success' => false, 'messages' => [['message' => 'secret-password leaked by supplier']]], 'body' => ['token' => 'secret-token']], 200)
            ->push($this->success(['token' => '']), 200)
            ->push($this->success(['token' => 'secret-token']), 302, ['Location' => 'https://127.0.0.1'])]);
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson($this->url().'/test')->assertStatus(502)->assertJsonPath('provider.integration.profiles.sandbox.status', 'error');
            $this->assertStringNotContainsString('secret-password', $response->getContent());
            $this->assertStringNotContainsString('secret-token', $response->getContent());
        }
        Http::assertSentCount(3);
    }

    public function test_departure_and_arrival_dictionaries_use_returned_location_types(): void
    {
        $this->install();
        Http::fake([
            '*/authenticationservice/login' => Http::response($this->success(['token' => 'ephemeral-secret-token'])),
            '*/productservice/getdepartures' => Http::response($this->success(['locations' => [['id' => 'EVN-CITY', 'name' => '<b>Yerevan</b>', 'type' => 2], ['id' => 'bad', 'name' => 'Bad', 'type' => 8]]])),
            '*/productservice/getarrivals' => Http::response($this->success(['locations' => [['id' => 'SSH-CITY', 'name' => 'Sharm El Sheikh', 'type' => 2]]])),
        ]);
        $this->postJson($this->url().'/dictionary/departures')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Yerevan')->assertJsonPath('data.0.type', 2);
        $this->postJson($this->url().'/dictionary/arrivals', ['departure_id' => 'EVN-CITY', 'departure_type' => 2])->assertOk()->assertJsonPath('data.0.id', 'SSH-CITY');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/getarrivals') && $request['ProductType'] === 1 && $request['DepartureLocations'] === [['Id' => 'EVN-CITY', 'Type' => 2]] && $request->hasHeader('Authorization', 'Bearer ephemeral-secret-token'));
        Http::assertSentCount(4);
    }

    public function test_search_normalizes_supplier_offers_without_inventing_prices_or_booking(): void
    {
        $this->install();
        $input = array_replace($this->searchInput(), ['adults' => '2', 'children' => ['7'], 'nights' => '7', 'departure_type' => '2']);
        $offer = ['offerId' => 'offer-1', 'checkIn' => $input['check_in'].'T00:00:00', 'night' => 7, 'rooms' => [['roomName' => '<b>Family</b>', 'boardName' => 'All inclusive']], 'price' => ['amount' => 1780.50, 'currency' => 'USD'], 'isAvailable' => true];
        Http::fake([
            '*/authenticationservice/login' => Http::response($this->success(['token' => 'ephemeral-secret-token'])),
            '*/productservice/pricesearch' => Http::response($this->success(['hotels' => [['name' => 'Test Resort', 'stars' => 5, 'offers' => [$offer, array_replace($offer, ['offerId' => 'unknown-price', 'price' => ['amount' => -1, 'currency' => 'XXX'], 'isAvailable' => 'true']), array_replace($offer, ['night' => 3]), array_replace($offer, ['rooms' => [null]])]]]])),
        ]);
        $result = $this->postJson($this->url().'/search', $input)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.room', 'Family')->assertJsonPath('data.0.price', '1780.5')->assertJsonPath('data.0.price_basis', 'party_total')->assertJsonPath('data.1.price', null)->assertJsonPath('data.1.available', false)->assertJsonPath('booking_enabled', false);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/pricesearch') && $request['RoomCriteria'] === [['Adult' => 2, 'ChildAges' => [7]]] && $request['CheckAllotment'] === true && $request['CheckStopSale'] === true && $request['Nationality'] === 'AM' && $request['currency'] === 'USD' && $request['CheckIn'] === $input['check_in']);
        Http::assertSentCount(2);
        $this->assertStringNotContainsString('ephemeral-secret-token', $result->getContent());
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('package_offers', 0);
    }

    public function test_bad_search_input_is_rejected_before_supplier_calls(): void
    {
        $this->install();
        $this->postJson($this->url().'/search', array_replace($this->searchInput(), ['children' => [18], 'check_in' => today()->subDay()->toDateString(), 'nights' => 29, 'departure_type' => 9]))->assertUnprocessable()->assertJsonValidationErrors(['children.0', 'check_in', 'nights', 'departure_type']);
        $this->postJson($this->url().'/dictionary/arrivals')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_inflight_results_cannot_overwrite_rotated_credentials(): void
    {
        $this->install();
        Http::fake(function () {
            $provider = TravelProvider::where('code', 'travelone')->firstOrFail();
            $profiles = $provider->integration_profiles;
            $profiles['sandbox']['version']++;
            $profiles['sandbox']['credentials']['password'] = 'rotated-secret';
            $provider->update(['integration_profiles' => $profiles]);

            return Http::response($this->success(['token' => 'ephemeral-secret-token']));
        });
        $this->postJson($this->url().'/test')->assertConflict();
        $profile = ProviderIntegration::profile(TravelProvider::firstOrFail(), 'sandbox');
        $this->assertSame('not_tested', $profile['status']);
        $this->assertSame('rotated-secret', $profile['credentials']['password']);
        $this->assertTrue(Cache::lock('provider-operation:travelone:sandbox', 30)->get());
    }
}
