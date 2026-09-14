<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\TravelProvider;
use App\Models\User;
use App\Services\Travel\ProviderSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function customer(string $email = 'travel@example.test'): User
    {
        return User::create(['name' => 'Travel Customer', 'email' => $email, 'password' => 'ExamplePass42!', 'role' => 'customer']);
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::where('role', 'admin')->firstOrFail());
    }

    private function order(?User $user = null): Booking
    {
        return Booking::create(['reference' => 'TEST-'.Str::random(10), 'name' => 'Test Guest', 'email' => $user?->email ?? 'guest@example.test', 'user_id' => $user?->id, 'type' => 'package', 'status' => 'new']);
    }

    private function quote(): array
    {
        return ['version' => 1, 'action' => 'quote', 'total_price' => 1000, 'currency' => 'USD', 'quote_terms' => 'Hotel and transfer. Free cancellation until the stated deadline.', 'quote_expires_at' => now()->addDays(2)->toIso8601String(), 'components' => [['type' => 'accommodation', 'title' => 'Test hotel', 'cost' => 700, 'sell_price' => 1000, 'currency' => 'USD', 'status' => 'pending']]];
    }

    public function test_registration_cannot_elevate_role_and_accounts_are_private(): void
    {
        $this->getJson('/api/v1/account/orders')->assertUnauthorized();
        $data = ['name' => 'New Customer', 'email' => 'New@example.test', 'password' => 'LongPassword42!', 'password_confirmation' => 'LongPassword42!', 'role' => 'admin'];
        $session = $this->postJson('/api/v1/account/register', $data)->assertCreated()->assertJsonMissingPath('user.password')->json();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'role' => 'customer']);
        $this->withToken($session['token'])->getJson('/api/admin/providers')->assertForbidden();
        $this->withToken($session['token'])->getJson('/api/v1/account/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/account/register', array_replace($data, ['email' => 'weak@example.test', 'password' => 'short', 'password_confirmation' => 'short']))->assertUnprocessable();
    }

    public function test_booking_idempotency_and_authenticated_ownership_ignore_client_prices(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);
        $data = ['type' => 'flight', 'name' => $user->name, 'email' => 'spoof@example.test', 'submission_key' => (string) Str::uuid(), 'status' => 'confirmed', 'total_price' => 1, 'user_id' => 1, 'request_details' => ['origin' => 'EVN', 'destination' => 'CDG', 'children' => [7]]];
        $first = $this->postJson('/api/v1/bookings', $data)->assertCreated()->json('reference');
        $this->postJson('/api/v1/bookings', $data)->assertCreated()->assertJsonPath('reference', $first);
        $this->assertSame(1, Booking::where('reference', $first)->count());
        $booking = Booking::where('reference', $first)->firstOrFail();
        $this->assertSame($user->id, $booking->user_id);
        $this->assertSame($user->email, $booking->email);
        $this->assertSame('new', $booking->status);
        $this->assertNull($booking->total_price);
        $this->postJson('/api/v1/bookings', array_replace($data, ['name' => 'Changed']))->assertConflict();
        Sanctum::actingAs($this->customer('second@example.test'));
        $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/account/orders/'.$booking->id.'/action', ['action' => 'cancel', 'version' => 1])->assertNotFound();
    }

    public function test_quote_accept_confirm_cancel_flow_and_private_notes(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $url = '/api/admin/travel/requests/'.$order->id;
        $this->admin();
        $this->putJson($url, ['version' => 1, 'action' => 'confirm', 'confirmation_reference' => 'FAKE'])->assertConflict();
        $this->putJson($url, array_replace($this->quote(), ['total_price' => 999]))->assertUnprocessable();
        $this->putJson($url, $this->quote())->assertOk()->assertJsonPath('status', 'quoted')->assertJsonPath('version', 2);
        $this->putJson($url, $this->quote())->assertConflict();
        $this->putJson($url, ['version' => 2, 'action' => 'note', 'note' => 'Private margin negotiation', 'internal' => true])->assertOk();
        Sanctum::actingAs($user);
        $customerData = $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonMissingPath('data.0.components.0.cost')->assertJsonCount(1, 'data.0.events')->json('data.0');
        $this->assertStringNotContainsString('Private margin', json_encode($customerData));
        $this->assertSame('1000.00', $customerData['events'][0]['snapshot']['total_price']);
        $this->postJson('/api/v1/account/orders/'.$order->id.'/action', ['version' => 2, 'action' => 'accept'])->assertConflict();
        $this->postJson('/api/v1/account/orders/'.$order->id.'/action', ['version' => 3, 'action' => 'accept'])->assertOk()->assertJsonPath('status', 'accepted');
        $this->admin();
        $this->putJson($url, ['version' => 4, 'action' => 'confirm', 'confirmation_reference' => 'REAL-123'])->assertUnprocessable();
        $this->putJson($url, ['version' => 4, 'action' => 'confirm', 'confirmation_reference' => 'REAL-123', 'component_references' => ['HOTEL-456']])->assertOk()->assertJsonPath('components.0.status', 'confirmed');
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/account/orders/'.$order->id.'/action', ['version' => 5, 'action' => 'cancel'])->assertOk()->assertJsonPath('status', 'cancellation_requested');
        $this->assertDatabaseHas('bookings', ['id' => $order->id, 'confirmation_reference' => 'REAL-123']);
        $this->admin();
        $this->putJson($url, ['version' => 6, 'action' => 'cancel'])->assertUnprocessable();
        $this->putJson($url, ['version' => 6, 'action' => 'cancel', 'note' => 'Supplier cancelled; no payment had been collected.'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->deleteJson('/api/admin/content/bookings/'.$order->id)->assertStatus(405);
    }

    public function test_expired_quotes_cannot_be_accepted_and_guest_consent_is_recorded(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $order->update(['status' => 'quoted', 'quote_expires_at' => now()->subMinute()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/account/orders/'.$order->id.'/action', ['action' => 'accept', 'version' => 1])->assertConflict();
        $guest = $this->order();
        $this->admin();
        $this->putJson('/api/admin/travel/requests/'.$guest->id, $this->quote())->assertOk();
        $this->putJson('/api/admin/travel/requests/'.$guest->id, ['action' => 'accept_guest', 'version' => 2])->assertUnprocessable();
        $this->putJson('/api/admin/travel/requests/'.$guest->id, ['action' => 'accept_guest', 'version' => 2, 'note' => 'Customer accepted by phone.'])->assertOk()->assertJsonPath('status', 'accepted');
    }

    public function test_provider_credentials_are_encrypted_write_only_and_sandbox_is_private(): void
    {
        $this->getJson('/api/admin/providers')->assertUnauthorized();
        $this->admin();
        $this->putJson('/api/admin/providers/geoapify', ['enabled' => true, 'environment' => 'sandbox', 'credentials' => ['api_key' => 'super-secret-test-key']])->assertOk()->assertJsonMissingPath('credentials');
        $raw = DB::table('travel_providers')->where('code', 'geoapify')->value('credentials');
        $this->assertStringNotContainsString('super-secret', $raw);
        $list = $this->getJson('/api/admin/providers')->assertOk()->getContent();
        $this->assertStringNotContainsString('super-secret', $list);
        $this->postJson('/api/v1/travel/search', ['service' => 'places', 'locale' => 'en', 'adults' => 2])->assertOk()->assertJsonCount(0, 'sources');
        Http::assertNothingSent();
        $this->putJson('/api/admin/providers/geoapify', ['enabled' => true, 'environment' => 'sandbox', 'credentials' => ['api_key' => '']])->assertOk();
        $this->assertSame('super-secret-test-key', TravelProvider::firstOrFail()->credentials['api_key']);
        $this->putJson('/api/admin/providers/geoapify', ['enabled' => false, 'environment' => 'sandbox', 'clear_credentials' => true])->assertOk()->assertJsonPath('configured', false);
        $this->putJson('/api/admin/providers/duffel', ['enabled' => true, 'environment' => 'live', 'credentials' => ['api_key' => 'duffel_test_example']])->assertUnprocessable();
    }

    public function test_supplier_failures_do_not_leak_secrets_or_hide_local_catalogue(): void
    {
        $provider = TravelProvider::create(['code' => 'geoapify', 'enabled' => true, 'environment' => 'live', 'credentials' => ['api_key' => 'do-not-leak']]);
        Http::fake(['api.geoapify.com/*' => Http::response(['error' => 'do-not-leak'], 401)]);
        $response = $this->postJson('/api/v1/travel/search', ['service' => 'places', 'locale' => 'en', 'adults' => 2, 'latitude' => 40.1, 'longitude' => 44.5])->assertOk()->assertJsonPath('sources.0.status', 'temporarily_unavailable');
        $this->assertNotEmpty($response->json('data'));
        $this->assertStringNotContainsString('do-not-leak', $response->getContent());
        $this->assertStringNotContainsString('do-not-leak', $provider->fresh()->last_error);
    }

    public function test_search_validates_dates_occupancy_locations_and_new_service_catalogue(): void
    {
        $this->postJson('/api/v1/travel/search', ['service' => 'flights', 'locale' => 'en', 'adults' => 2, 'origin' => 'EVN', 'destination' => 'EVN', 'start_date' => now()->subDay()->toDateString()])->assertUnprocessable();
        $this->postJson('/api/v1/travel/search', ['service' => 'hotels', 'locale' => 'en', 'adults' => 2, 'rooms' => 3])->assertUnprocessable();
        $service = Service::firstOrFail()->replicate();
        $service->fill(['slug' => 'test-airport-transfer', 'type' => 'transfer', 'title' => ['hy' => 'Տրանսֆեր', 'en' => 'Airport shuttle'], 'active' => true])->save();
        $input = ['service' => 'transfers', 'locale' => 'en', 'adults' => 2, 'destination' => 'shuttle', 'departure_time' => '12:00', 'start_date' => now()->addWeek()->toDateString()];
        $this->postJson('/api/v1/travel/search', $input)->assertOk()->assertJsonPath('data.0.url', '/en/services/test-airport-transfer');
        $service->update(['active' => false]);
        $this->postJson('/api/v1/travel/search', $input)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_adapters_use_expected_protocols_and_safe_partner_links(): void
    {
        Http::fake([
            'api.duffel.com/*' => Http::response(['data' => ['offers' => [['id' => 'off_1', 'owner' => ['name' => 'Air Test'], 'total_amount' => '450.00', 'total_currency' => 'USD', 'expires_at' => now()->addHour()->toIso8601String(), 'slices' => [['segments' => [['origin' => ['iata_code' => 'EVN'], 'destination' => ['iata_code' => 'CDG'], 'departing_at' => '2027-01-10T12:00:00', 'operating_carrier' => ['name' => 'Actual Operating Airline']]]]]]]]]),
            'api.geoapify.com/*' => Http::response(['features' => [['properties' => ['place_id' => 'place_1', 'name' => 'Museum', 'formatted' => 'Main Street', 'lat' => 40.1, 'lon' => 44.5]]]]),
            'api.sandbox.viator.com/*' => Http::response(['products' => ['results' => [['productCode' => '123P1', 'title' => 'Guided tour', 'pricing' => ['summary' => ['fromPrice' => 25], 'currency' => 'USD'], 'productUrl' => 'https://www.viator.com/tours/test/123P1']]]]),
            'demandapi-sandbox.booking.com/3.1/accommodations/search' => Http::response(['data' => [['id' => 10004, 'currency' => 'EUR', 'price' => ['total' => 300], 'url' => 'https://www.booking.com/hotel/test.html']]]),
            'demandapi-sandbox.booking.com/3.1/accommodations/details' => Http::response(['data' => [['id' => 10004, 'name' => ['en' => 'Example Hotel']]]]),
            'api.test.hotelbeds.com/*' => Http::response(['services' => [['id' => 1, 'rateKey' => 'rate_1', 'vehicle' => ['name' => 'Private car'], 'price' => ['totalAmount' => 50, 'currencyId' => 'EUR']]]]),
        ]);
        $input = ['locale' => 'en', 'origin' => 'EVN', 'destination' => 'CDG', 'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonth()->addDays(3)->toDateString(), 'adults' => 2, 'children' => [2, 7, 14], 'rooms' => 1, 'latitude' => 48.8, 'longitude' => 2.3, 'booker_country' => 'am'];
        $search = app(ProviderSearch::class);
        foreach (['duffel', 'geoapify', 'viator', 'booking', 'hotelbeds'] as $code) {
            $provider = new TravelProvider(['code' => $code, 'environment' => 'sandbox', 'credentials' => ['api_key' => 'test-key', 'secret' => 'test-secret', 'affiliate_id' => '123']]);
            $results = $search->search($provider, $input);
            $this->assertCount(1, $results, $code);
            if ($code === 'duffel') {
                $this->assertStringContainsString('Actual Operating Airline', $results[0]['description']);
            }
        }
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.duffel.com') && $r->hasHeader('Duffel-Version', 'v2') && count($r['data']['passengers']) === 5 && count($r['data']['slices']) === 2);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'hotelbeds.com') && strlen($r->header('X-Signature')[0]) === 64 && str_ends_with($r->url(), '/3/1/1'));
        $this->assertNull(ProviderSearch::safeUrl('https://www.viator.com.evil.test/pay', ['viator.com']));
        $this->assertNull(ProviderSearch::safeUrl('javascript:alert(1)', ['viator.com']));
    }

    public function test_password_reset_is_single_use_customer_only_and_revokes_sessions(): void
    {
        $user = $this->customer();
        $user->createToken('old-session');
        $token = Password::createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'UpdatedPassword42!', 'password_confirmation' => 'UpdatedPassword42!'];
        $this->postJson('/api/v1/account/reset-password', $payload)->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/account/reset-password', $payload)->assertUnprocessable();
        $admin = User::where('role', 'admin')->firstOrFail();
        $adminToken = Password::createToken($admin);
        $this->postJson('/api/v1/account/reset-password', [...$payload, 'email' => $admin->email, 'token' => $adminToken])->assertUnprocessable();
        $this->postJson('/api/v1/account/forgot-password', ['email' => $user->email, 'locale' => 'hy'])->assertStatus(503);
    }

    public function test_package_partial_failure_blocks_full_confirmation(): void
    {
        $order = $this->order();
        $this->admin();
        $url = '/api/admin/travel/requests/'.$order->id;
        $this->putJson($url, $this->quote())->assertOk();
        $this->putJson($url, ['action' => 'accept_guest', 'version' => 2, 'note' => 'Guest accepted by phone'])->assertOk();
        $this->putJson($url, ['action' => 'component', 'version' => 3, 'component_index' => 0, 'component_status' => 'failed', 'note' => 'Hotel could not confirm the room.'])->assertOk()->assertJsonPath('status', 'accepted')->assertJsonPath('components.0.status', 'failed');
        $this->putJson($url, ['action' => 'confirm', 'version' => 4, 'confirmation_reference' => 'TEST', 'component_references' => ['HOTEL']])->assertUnprocessable();
        $this->putJson($url, ['action' => 'component', 'version' => 4, 'component_index' => 0, 'component_status' => 'confirmed', 'supplier_reference' => 'HOTEL-123', 'note' => 'Hotel has now confirmed the room.'])->assertOk()->assertJsonPath('status', 'accepted');
        $this->putJson($url, ['action' => 'confirm', 'version' => 5, 'confirmation_reference' => 'TEST', 'component_references' => ['HOTEL-123']])->assertOk();
    }

    public function test_location_lookup_requires_live_configuration_and_removes_upstream_fields(): void
    {
        $this->getJson('/api/v1/travel/locations?search=Paris&locale=en&kind=city')->assertOk()->assertJsonPath('available', false);
        Http::assertNothingSent();
        TravelProvider::create(['code' => 'geoapify', 'enabled' => true, 'environment' => 'live', 'credentials' => ['api_key' => 'hidden-key']]);
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [['formatted' => 'Paris, France', 'lat' => 48.8566, 'lon' => 2.3522, 'apiKey' => 'hidden-key']]])]);
        $this->getJson('/api/v1/travel/locations?search=Paris&locale=en&kind=city')->assertOk()->assertJsonPath('data.0.label', 'Paris, France')->assertJsonMissingPath('data.0.apiKey');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/geocode/autocomplete') && str_contains($r->url(), 'type=city'));
        $this->withToken('expired-token')->postJson('/api/v1/bookings', ['name' => 'Expired', 'email' => 'expired@example.test'])->assertUnauthorized();
    }
}
