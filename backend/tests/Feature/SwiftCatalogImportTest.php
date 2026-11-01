<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Services\SwiftCatalogImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SwiftCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        return json_decode(file_get_contents(database_path('data/swift-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_preview_is_read_only_and_reimport_is_idempotent_without_overwriting_other_services(): void
    {
        $catalog = $this->catalog();
        $importer = app(SwiftCatalogImport::class);
        $baseline = Service::count();
        $this->artisan('swift:import-catalog')->assertSuccessful();
        $this->assertDatabaseCount('services', $baseline);
        $manual = Service::create([...$catalog['records'][0], 'slug' => 'manual-car', 'supplier' => null]);
        $this->assertSame(['cars' => 21, 'transfers' => 23, 'applied' => true], $importer->run($catalog, true));
        $importer->run($catalog, true);
        $this->assertDatabaseCount('services', $baseline + 45);
        $this->assertNull($manual->fresh()->supplier);
        $this->assertDatabaseHas('services', ['slug' => $catalog['records'][0]['slug'], 'price_from' => $catalog['records'][0]['price_from']]);
    }

    public function test_invalid_price_basis_is_rejected_before_any_write(): void
    {
        $catalog = $this->catalog();
        $baseline = Service::count();
        $catalog['records'][0]['price_from'] += 1;
        try {
            app(SwiftCatalogImport::class)->run($catalog, true);
            $this->fail('A mismatched starting price was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('services', $baseline);
        }
    }

    public function test_manual_slug_collision_rolls_back_the_entire_import(): void
    {
        $catalog = $this->catalog();
        $baseline = Service::count();
        Service::create([...$catalog['records'][1], 'supplier' => null]);
        try {
            app(SwiftCatalogImport::class)->run($catalog, true);
            $this->fail('A manual GoVista service was overwritten.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('services', $baseline + 1);
            $this->assertNull(Service::where('slug', $catalog['records'][1]['slug'])->first()->supplier);
        }
    }

    public function test_imported_catalog_is_localized_and_inquiries_keep_trusted_supplier_provenance(): void
    {
        $existingCars = Service::where('type', 'transport')->count();
        $existingTransfers = Service::where('type', 'transfer')->count();
        app(SwiftCatalogImport::class)->run($this->catalog(), true);
        foreach (['hy', 'ru', 'en'] as $locale) {
            $this->getJson('/api/v1/services?type=transport&locale='.$locale)->assertOk()->assertJsonCount($existingCars + 21);
            $this->getJson('/api/v1/services?type=transfer&locale='.$locale)->assertOk()->assertJsonCount($existingTransfers + 23);
        }
        $transfer = Service::where('type', 'transfer')->where('supplier->code', 'swift')->firstOrFail();
        $payload = ['type' => 'transfer', 'item_id' => $transfer->id, 'item_title' => 'Untrusted client title', 'name' => 'Catalog QA', 'email' => 'catalog-test@example.test', 'participants' => 2, 'locale' => 'hy'];
        $this->postJson('/api/v1/bookings', [...$payload, 'request_details' => ['supplier' => ['code' => 'fake']]])->assertUnprocessable();
        $this->postJson('/api/v1/bookings', $payload)->assertCreated();
        $booking = Booking::firstOrFail();
        $this->assertSame('swift', data_get($booking->request_details, 'supplier.code'));
        $this->assertSame($transfer->supplier['reference'], data_get($booking->request_details, 'supplier.reference'));
        $this->assertSame($transfer->title['hy'], $booking->item_title);
    }
}
