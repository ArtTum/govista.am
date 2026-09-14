<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_draft_and_scheduled_posts_are_hidden_from_every_public_endpoint(): void
    {
        $post = Post::firstOrFail();
        foreach ([null, now()->addWeek()] as $date) {
            $post->update(['published_at' => $date]);
            $this->getJson('/api/v1/posts/'.$post->slug)->assertNotFound();
            $this->assertNotContains($post->id, array_column($this->getJson('/api/v1/posts')->assertOk()->json('data'), 'id'));
            $this->assertNotContains($post->id, array_column($this->getJson('/api/v1/home')->assertOk()->json('posts'), 'id'));
        }
        $post->update(['published_at' => now()->subMinute()]);
        $this->getJson('/api/v1/posts/'.$post->slug)->assertOk();
    }

    public function test_search_finds_records_beyond_the_first_page_and_preserves_filters(): void
    {
        $source = Tour::firstOrFail();
        for ($index = 0; $index < 50; $index++) {
            $tour = $source->replicate();
            $tour->slug = 'audit-tour-'.$index;
            $tour->title = ['hy' => 'Հատուկ որոնում '.$index, 'ru' => 'Поисковая поездка '.$index, 'en' => 'Unique journey '.$index];
            $tour->sort_order = 1000 + $index;
            $tour->save();
        }
        $query = http_build_query(['locale' => 'en', 'search' => '  unique journey 49  ', 'travel_scope' => 'domestic', 'per_page' => 12]);
        $this->getJson('/api/v1/tours?'.$query)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'audit-tour-49');
        $this->getJson('/api/v1/tours?locale=en&search=Unique%20journey&travel_scope=international')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/tours?per_page=48&page=2')->assertOk()->assertJsonPath('meta.total', 74)->assertJsonCount(26, 'data');
        $this->getJson('/api/v1/tours?'.http_build_query(['locale' => 'hy', 'search' => 'Հատուկ որոնում 49']))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_booking_rejects_invalid_dates_and_catalog_references(): void
    {
        $guest = ['name' => 'Audit Guest', 'email' => 'audit@example.test'];
        $this->postJson('/api/v1/bookings', $guest + ['start_date' => now()->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->postJson('/api/v1/bookings', $guest + ['end_date' => now()->addWeek()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->postJson('/api/v1/bookings', $guest + ['start_date' => '2027-02-30'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $dates = ['start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString()];
        $this->postJson('/api/v1/bookings', $guest + $dates + ['type' => 'accommodation'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->postJson('/api/v1/bookings', $guest + $dates + ['type' => 'transport'])->assertCreated();
        $this->postJson('/api/v1/bookings', $guest + ['type' => 'tour', 'item_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $service = Service::where('type', 'accommodation')->firstOrFail();
        $this->postJson('/api/v1/bookings', $guest + ['type' => 'transport', 'item_id' => $service->id])->assertUnprocessable()->assertJsonValidationErrors('item_id');
    }

    public function test_booking_stores_catalog_title_and_preserves_guest_preferences(): void
    {
        $tour = Tour::firstOrFail();
        $date = now()->addMonth()->toDateString();
        $response = $this->postJson('/api/v1/bookings', [
            'name' => 'Audit Guest', 'email' => 'audit@example.test', 'locale' => 'en',
            'type' => $tour->type, 'item_id' => $tour->id, 'item_title' => 'Forged title',
            'start_date' => $date, 'participants' => 7,
        ])->assertCreated();
        $this->assertDatabaseHas('bookings', [
            'reference' => $response->json('reference'), 'item_title' => $tour->title['en'],
            'participants' => 7,
        ]);
        $tour->update(['active' => false]);
        $this->postJson('/api/v1/bookings', ['name' => 'Audit Guest', 'email' => 'audit@example.test', 'type' => $tour->type, 'item_id' => $tour->id])->assertUnprocessable();
    }

    public function test_admin_rejects_malformed_translations_and_searches_titles(): void
    {
        Sanctum::actingAs(User::where('role', 'admin')->firstOrFail());
        $tour = Tour::firstOrFail();
        $payload = $tour->toArray();
        $payload['title'] = ['hy' => ['broken' => 'object'], 'en' => 'Title'];
        $payload['gallery'] = [['invalid']];
        $this->putJson('/api/admin/content/tours/'.$tour->id, $payload)->assertUnprocessable()->assertJsonValidationErrors(['title.hy', 'gallery.0']);
        $payload['title'] = ['en' => 'Title'];
        $this->putJson('/api/admin/content/tours/'.$tour->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('title.hy');
        $tour->update(['title' => ['hy' => 'Որոնման հատուկ վերնագիր', 'en' => 'A distinct title']]);
        $this->getJson('/api/admin/content/tours?'.http_build_query(['search' => 'Որոնման հատուկ']))->assertOk()->assertJsonPath('data.0.id', $tour->id);
        $this->postJson('/api/admin/content/bookings', ['status' => 'new'])->assertStatus(405);
        $this->postJson('/api/admin/content/contact-messages', ['status' => 'new'])->assertStatus(405);
    }

    public function test_empty_translation_falls_back_and_settings_endpoint_matches_home(): void
    {
        $tour = Tour::firstOrFail();
        $tour->update(['title' => ['hy' => 'Հայերեն վերնագիր', 'en' => '', 'ru' => null]]);
        $this->getJson('/api/v1/tours/'.$tour->slug.'?locale=en')->assertOk()->assertJsonPath('tour.title', 'Հայերեն վերնագիր');
        foreach (['hy', 'ru', 'en'] as $locale) {
            $settings = $this->getJson('/api/v1/settings?locale='.$locale)->assertOk()->json();
            $this->assertSame($settings, $this->getJson('/api/v1/home?locale='.$locale)->assertOk()->json('settings'));
        }
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonPath('meta.total', 4);
    }
}
