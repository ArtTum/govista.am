<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\PackageOffer;
use App\Models\TravelProvider;
use App\Models\User;
use App\Services\Travel\TourvisorSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackagePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        $user = User::where('role', 'admin')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function settings(): array
    {
        return ['partnership_status' => 'approved', 'contract_reference' => 'TEST-CONTRACT', 'content_rights_confirmed' => true, 'evn_confirmed' => true, 'contact_email' => 'private@example.test', 'notes' => 'PRIVATE-NOTES', 'departure_id' => 88, 'destination_ids' => ['sharm' => ['country_id' => 5, 'region_id' => 77]]];
    }

    private function provider(string $code = 'anriva'): TravelProvider
    {
        return TravelProvider::create(['code' => $code, 'settings' => $this->settings(), 'enabled' => $code === 'tourvisor', 'environment' => 'live', 'credentials' => $code === 'tourvisor' ? ['api_key' => 'SECRET-KEY'] : []]);
    }

    private function offer(array $replace = []): array
    {
        return array_replace(['provider_code' => 'anriva', 'supplier_reference' => 'SUPPLIER-PRIVATE-123', 'title' => ['hy' => 'Փորձնական փաթեթ', 'en' => 'Test package', 'ru' => ''], 'description' => ['hy' => 'Նկարագրություն'], 'destination_code' => 'sharm', 'origin' => 'EVN', 'hotel_name' => 'Test Hotel', 'stars' => 5, 'room_type' => 'Double', 'meal_plan' => 'AI', 'departure_date' => now()->addMonth()->toDateString(), 'nights' => 7, 'adults' => 2, 'children' => [], 'inclusions' => ['flight', 'hotel', 'transfer'], 'terms' => ['hy' => 'Ստուգված չեղարկման պայմաններ'], 'exclusions' => ['hy' => 'Վիզան առանձին է'], 'price' => 1500, 'cost' => 1000, 'currency' => 'USD', 'price_basis' => 'party_total', 'status' => 'published', 'availability' => 'on_request', 'valid_until' => now()->addDays(2)->toIso8601String(), 'price_checked_at' => now()->toIso8601String(), 'internal_notes' => 'PRIVATE-COST-NOTE'], $replace);
    }

    public function test_provider_catalogue_is_public_but_credentials_contacts_and_contracts_are_private(): void
    {
        $this->provider('tourvisor');
        $public = $this->getJson('/api/v1/packages/options')->assertOk()->assertJsonCount(8, 'providers')->assertJsonCount(12, 'destinations')->assertJsonPath('live_search', true)->assertJsonPath('payments_enabled', false)->getContent();
        foreach (['SECRET-KEY', 'PRIVATE-NOTES', 'private@example.test', 'TEST-CONTRACT', 'departure_id'] as $private) {
            $this->assertStringNotContainsString($private, $public);
        }
        $this->getJson('/api/admin/package-offers')->assertUnauthorized();
        Sanctum::actingAs(User::create(['name' => 'Customer', 'email' => 'customer@example.test', 'password' => 'LocalPass42!', 'role' => 'customer']));
        $this->postJson('/api/admin/package-offers', $this->offer())->assertForbidden();
        $this->admin();
        $this->getJson('/api/admin/providers')->assertOk()->assertJsonCount(13)->assertJsonMissingPath('7.credentials');
    }

    public function test_manual_providers_cannot_be_mistaken_for_working_api_connections(): void
    {
        $this->admin();
        $payload = ['enabled' => false, 'environment' => 'sandbox', 'settings' => $this->settings()];
        foreach (['anriva', 'tez_tour', 'sletat', 'tbo'] as $code) {
            $this->putJson('/api/admin/providers/'.$code, $payload)->assertOk()->assertJsonPath('configured', false)->assertJsonPath('partnership_ready', true);
            $this->putJson('/api/admin/providers/'.$code, [...$payload, 'enabled' => true])->assertUnprocessable();
            $this->postJson('/api/admin/providers/'.$code.'/test')->assertUnprocessable();
        }
        $this->putJson('/api/admin/providers/tourvisor', ['enabled' => true, 'environment' => 'live', 'credentials' => ['api_key' => 'SECRET-KEY'], 'settings' => [...$this->settings(), 'evn_confirmed' => false]])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_publication_requires_permission_dates_terms_and_recent_prices(): void
    {
        $this->admin();
        $this->postJson('/api/admin/package-offers', $this->offer())->assertUnprocessable()->assertJsonValidationErrors('provider_code');
        $this->provider();
        foreach (['valid_until' => now()->subMinute()->toIso8601String(), 'departure_date' => now()->subDay()->toDateString(), 'price_checked_at' => now()->subDays(31)->toIso8601String(), 'terms' => []] as $key => $value) {
            $this->postJson('/api/admin/package-offers', $this->offer([$key => $value]))->assertUnprocessable();
        }
        $this->postJson('/api/admin/package-offers', $this->offer(['price_basis' => 'per_person', 'children' => [5]]))->assertUnprocessable();
        $this->postJson('/api/admin/package-offers', $this->offer(['image' => '/storage/../private.txt']))->assertUnprocessable();
        $offer = $this->postJson('/api/admin/package-offers', $this->offer())->assertCreated()->assertJsonPath('version', 1)->json();
        $this->postJson('/api/admin/package-offers', $this->offer())->assertUnprocessable()->assertJsonValidationErrors('supplier_reference');
        $this->putJson('/api/admin/package-offers/'.$offer['id'], [...$this->offer(), 'version' => 1, 'price' => 1700])->assertOk()->assertJsonPath('version', 2);
        $this->putJson('/api/admin/package-offers/'.$offer['id'], [...$this->offer(), 'version' => 1, 'price' => 1600])->assertConflict();
    }

    public function test_catalogue_filters_and_hides_unpublished_expired_sold_out_and_paused_supplier_offers(): void
    {
        $provider = $this->provider();
        $base = PackageOffer::create($this->offer());
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['valid_until' => now()->subMinute()], ['departure_date' => today()->subDay()], ['availability' => 'sold_out'], ['price_checked_at' => now()->subDays(31)]] as $index => $replace) {
            PackageOffer::create($this->offer([...$replace, 'supplier_reference' => 'HIDDEN-'.$index]));
        }
        $response = $this->getJson('/api/v1/packages/offers?locale=ru&adults=2&origin=EVN&destination_code=sharm&meal_plan=AI&stars=4')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Փորձնական փաթեթ')->assertJsonMissingPath('data.0.cost');
        foreach (['PRIVATE-COST-NOTE', 'SUPPLIER-PRIVATE-123', 'contract_reference'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->getJson('/api/v1/packages/offers?locale=en&adults=2&children[]=7')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/packages/offers?locale=en&provider=tbo')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/packages/offers?locale=en&nights_min=10&nights_max=2')->assertUnprocessable();
        $provider->update(['settings' => [...$this->settings(), 'partnership_status' => 'paused']]);
        $this->getJson('/api/v1/packages/offers/'.$base->id.'?locale=en')->assertNotFound();
        $this->getJson('/api/v1/packages/offers?locale=en')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_selected_package_requests_keep_a_trusted_snapshot_and_never_create_paid_orders(): void
    {
        $this->provider();
        $offer = PackageOffer::create($this->offer());
        $customer = User::create(['name' => 'Customer', 'email' => 'snapshot@example.test', 'password' => 'LocalPass42!', 'role' => 'customer']);
        Sanctum::actingAs($customer);
        $payload = ['type' => 'package', 'name' => 'Guest', 'email' => 'snapshot@example.test', 'locale' => 'en', 'submission_key' => (string) Str::uuid(), 'item_title' => 'Spoofed', 'total_price' => 1, 'status' => 'confirmed', 'request_details' => ['package_offer_id' => $offer->id, 'provider' => 'tbo', 'adults' => 9]];
        $reference = $this->postJson('/api/v1/bookings', $payload)->assertCreated()->json('reference');
        $this->postJson('/api/v1/bookings', $payload)->assertCreated()->assertJsonPath('reference', $reference);
        $booking = Booking::where('reference', $reference)->firstOrFail();
        $this->assertSame('new', $booking->status);
        $this->assertNull($booking->total_price);
        $this->assertSame('Test package', $booking->item_title);
        $this->assertSame(2, $booking->participants);
        $this->assertSame('anriva', $booking->request_details['provider']);
        $this->assertSame('1500.00', $booking->request_details['package_snapshot']['price']);
        $offer->update(['price' => 2000]);
        $response = $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonPath('data.0.request_details.package_snapshot.price', '1500.00')->getContent();
        $this->assertStringNotContainsString('SUPPLIER-PRIVATE', $response);
        $this->assertStringNotContainsString('PRIVATE-COST', $response);
        $offer->update(['availability' => 'sold_out']);
        $this->postJson('/api/v1/bookings', [...$payload, 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_csv_preview_is_atomic_draft_only_and_commit_is_idempotent(): void
    {
        $this->admin();
        $this->getJson('/api/admin/package-offers/template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = "supplier_reference,title_hy,destination_code,children\nIMPORT-1,Ներմուծված փաթեթ,sharm,8|5\nIMPORT-2,Երկրորդ փաթեթ,dubai,\n";
        $preview = $this->postJson('/api/admin/package-offers/import/preview', ['provider_code' => 'anriva', 'csv' => $csv])->assertOk()->assertJsonCount(0, 'errors')->assertJsonPath('count', 2)->json();
        $this->assertDatabaseCount('package_offers', 0);
        $this->postJson('/api/admin/package-offers/import/commit', ['token' => $preview['token']])->assertOk()->assertJsonPath('created', 2);
        $this->postJson('/api/admin/package-offers/import/commit', ['token' => $preview['token']])->assertOk()->assertJsonPath('created', 2);
        $this->assertDatabaseCount('package_offers', 2);
        $this->assertSame([5, 8], PackageOffer::first()->children);
        $this->assertSame('draft', PackageOffer::first()->status);
        $this->getJson('/api/v1/packages/offers?locale=en')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/admin/package-offers/import/preview', ['provider_code' => 'anriva', 'csv' => $csv])->assertOk()->assertJsonPath('token', null)->assertJsonCount(2, 'errors');
    }

    public function test_import_rejects_invalid_rows_and_other_admin_cannot_commit_a_preview(): void
    {
        $this->admin();
        $this->postJson('/api/admin/package-offers/import/preview', ['provider_code' => 'anriva', 'csv' => "supplier_reference,title_hy,destination_code\nDUP,Title,sharm\nDUP,Title,dubai\nINVALID,Title,invalid\n"])->assertOk()->assertJsonCount(2, 'errors')->assertJsonPath('token', null);
        $this->postJson('/api/admin/package-offers/import/preview', ['provider_code' => 'anriva', 'csv' => "supplier_reference,title_hy,destination_code,status\n1,Title,dubai,published"])->assertUnprocessable();
        $preview = $this->postJson('/api/admin/package-offers/import/preview', ['provider_code' => 'anriva', 'csv' => "supplier_reference,title_hy,destination_code\nUNIQUE,Title,dubai\n"])->assertOk()->json();
        Sanctum::actingAs(User::create(['name' => 'Second admin', 'email' => 'second-admin@example.test', 'password' => 'LocalPass42!', 'role' => 'admin']));
        $this->postJson('/api/admin/package-offers/import/commit', ['token' => $preview['token']])->assertStatus(410);
        $this->assertDatabaseCount('package_offers', 0);
    }

    private function liveInput(): array
    {
        return ['locale' => 'en', 'origin' => 'EVN', 'destination_code' => 'sharm', 'date_from' => now()->addMonth()->toDateString(), 'date_to' => now()->addMonth()->addDay()->toDateString(), 'nights_min' => 7, 'nights_max' => 8, 'adults' => 2, 'children' => [5, 8], 'direct_only' => true];
    }

    public function test_live_search_needs_configuration_and_strict_input_before_using_supplier_quota(): void
    {
        $this->postJson('/api/v1/packages/search', $this->liveInput())->assertOk()->assertJsonPath('available', false);
        $this->postJson('/api/v1/packages/search', [...$this->liveInput(), 'date_from' => 'not-a-date'])->assertUnprocessable();
        $this->postJson('/api/v1/packages/search', [...$this->liveInput(), 'origin' => 'LHR'])->assertUnprocessable();
        $this->postJson('/api/v1/packages/search', [...$this->liveInput(), 'date_to' => now()->addMonths(3)->toDateString()])->assertUnprocessable();
        $this->postJson('/api/v1/packages/search', [...$this->liveInput(), 'children' => [1, 2, 3, 4]])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_tourvisor_search_polling_prices_and_credential_rotation(): void
    {
        $provider = $this->provider('tourvisor');
        Http::fake([
            'api.tourvisor.ru/search/api/v1/tours/search?*' => Http::response(['searchId' => 123]),
            'api.tourvisor.ru/search/api/v1/tours/search/123/status*' => Http::response(['searchId' => 123, 'progress' => 100, 'status' => 'finished']),
            'api.tourvisor.ru/search/api/v1/tours/search/123?*' => Http::response([['name' => '<b>Hotel</b>', 'category' => 5, 'tours' => [['id' => 'offer-1', 'date' => now()->addMonth()->toDateString(), 'nights' => 7, 'adults' => 2, 'childs' => 2, 'price' => 1600, 'currency' => 'USD', 'operator' => ['name' => 'Operator'], 'meal' => ['name' => 'AI'], 'fuelCharge' => 50], ['id' => 'offer-2', 'date' => now()->addMonth()->toDateString(), 'nights' => 7, 'adults' => 2, 'childs' => 2, 'price' => 1800, 'currency' => 'CU']]]]),
        ]);
        $token = $this->postJson('/api/v1/packages/search', $this->liveInput())->assertOk()->assertJsonPath('available', true)->assertHeader('Cache-Control', 'no-store, private')->json('token');
        $this->getJson('/api/v1/packages/search/'.$token)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Hotel')->assertJsonPath('data.0.price_basis', 'indicative_total')->assertJsonPath('data.1.price', null)->assertJsonPath('complete', true);
        $this->getJson('/api/v1/packages/search/'.$token)->assertOk();
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'search?') && $request->hasHeader('Authorization', 'Bearer SECRET-KEY') && $request['regionIds'] === '77' && $request['childs'] === '5,8' && $request['currency'] === 'CU' && $request['onlyDirect'] === 'true');
        $payload = ['type' => 'package', 'name' => 'Traveller', 'email' => 'live@example.test', 'locale' => 'en', 'request_details' => ['provider' => 'tourvisor', 'search_token' => $token, 'offer_id' => 'offer-1']];
        $this->postJson('/api/v1/bookings', $payload)->assertCreated();
        $this->postJson('/api/v1/bookings', [...$payload, 'request_details' => [...$payload['request_details'], 'offer_id' => 'invented']])->assertUnprocessable();
        $provider->update(['credentials' => ['api_key' => 'ROTATED-SECRET']]);
        $this->getJson('/api/v1/packages/search/'.$token)->assertStatus(410);
        $this->postJson('/api/v1/bookings', $payload)->assertUnprocessable();
    }

    public function test_supplier_errors_never_expose_keys_or_create_a_fake_connected_status(): void
    {
        $this->provider('tourvisor');
        Http::fake(['api.tourvisor.ru/*' => Http::response(['error' => 'SECRET-KEY'], 401)]);
        $response = $this->postJson('/api/v1/packages/search', $this->liveInput())->assertStatus(502)->getContent();
        $this->assertStringNotContainsString('SECRET-KEY', $response);
        $this->assertSame('error', TravelProvider::where('code', 'tourvisor')->first()->status);
        $this->admin();
        $response = $this->postJson('/api/admin/providers/tourvisor/test')->assertStatus(502)->getContent();
        $this->assertStringNotContainsString('SECRET-KEY', $response);
    }

    public function test_failed_polls_are_limited_and_locked_without_repeating_supplier_calls(): void
    {
        $provider = $this->provider('tourvisor');
        $token = Str::random(48);
        $key = 'package-search:'.$token;
        Cache::put($key, ['id' => '123', 'input' => $this->liveInput(), 'fingerprint' => app(TourvisorSearch::class)->fingerprint($provider), 'polls' => 0, 'expires_at' => now()->addMinutes(10)->timestamp], 600);
        Http::fake(['api.tourvisor.ru/*' => Http::response([], 503)]);
        $this->getJson('/api/v1/packages/search/'.$token)->assertStatus(502);
        $this->getJson('/api/v1/packages/search/'.$token)->assertStatus(429);
        $this->assertSame(1, Cache::get($key)['polls']);
        Http::assertSentCount(1);
        $this->travel(4)->seconds();
        $lock = Cache::lock('package-search-lock:'.$token, 25);
        $lock->get();
        try {
            $this->getJson('/api/v1/packages/search/'.$token)->assertStatus(429);
        } finally {
            $lock->release();
        }
        Http::assertSentCount(1);
    }

    public function test_malformed_supplier_dates_and_wrong_travellers_are_not_presented_as_matching_offers(): void
    {
        $provider = $this->provider('tourvisor');
        $valid = ['id' => 'valid', 'date' => $this->liveInput()['date_from'], 'nights' => 7, 'adults' => 2, 'childs' => 2, 'price' => 1200, 'currency' => 'USD'];
        Http::fake([
            'api.tourvisor.ru/search/api/v1/tours/search/123/status*' => Http::response(['progress' => 100]),
            'api.tourvisor.ru/search/api/v1/tours/search/123?*' => Http::response([['name' => 'Hotel', 'tours' => [$valid, [...$valid, 'id' => 'bad-date', 'date' => '2026-02-31'], [...$valid, 'id' => 'wrong-adults', 'adults' => 3], [...$valid, 'id' => 'wrong-children', 'childs' => 0], [...$valid, 'id' => 'wrong-nights', 'nights' => 0]]]]),
        ]);
        $result = app(TourvisorSearch::class)->results($provider,'123',$this->liveInput());
        $this->assertCount(1,$result['data']);
        $this->assertSame('valid',$result['data'][0]['id']);
    }
}
