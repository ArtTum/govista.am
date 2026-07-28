<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_content_endpoints_work_for_every_locale_and_seeded_slug(): void
    {
        $locales = ['hy', 'ru', 'en'];
        $tours = [
            'garni-geghard-symphony',
            'khor-virap-noravank-areni',
            'sevan-dilijan-forest',
            'tatev-wings-private',
            'armenia-seven-day-signature',
            'gyumri-cultural-day',
            'dubai-city-desert',
            'paris-romantic-escape',
            'montenegro-adriatic',
        ];
        $destinations = ['yerevan', 'lake-sevan', 'dilijan', 'tatev', 'areni', 'garni'];
        $posts = ['first-time-armenia-guide', 'best-season-armenia', 'armenian-flavors'];
        $pages = ['about', 'privacy', 'terms'];

        $localizedTitles = [];
        $localizedDurations = [];

        foreach ($locales as $locale) {
            $home = $this->getJson("/api/v1/home?locale={$locale}")
                ->assertOk()
                ->assertJsonStructure([
                    'settings',
                    'featured_tours',
                    'destinations',
                    'services',
                    'posts',
                    'testimonials',
                    'faqs',
                ])
                ->json();

            $this->assertNotEmpty($home['settings']['hero_title']);
            $this->assertCount(6, $home['featured_tours']);
            $this->assertCount(6, $home['destinations']);
            $this->assertCount(4, $home['services']);
            $localizedTitles[$locale] = $home['featured_tours'][0]['title'];
            $localizedDurations[$locale] = $home['featured_tours'][0]['duration'];

            $this->getJson("/api/v1/tours?locale={$locale}&per_page=48")
                ->assertOk()
                ->assertJsonCount(24, 'data')
                ->assertJsonPath('meta.total', 24);

            foreach ($tours as $slug) {
                $this->getJson("/api/v1/tours/{$slug}?locale={$locale}")
                    ->assertOk()
                    ->assertJsonPath('tour.slug', $slug)
                    ->assertJsonStructure(['tour' => ['title', 'description'], 'related']);
            }

            $this->getJson("/api/v1/destinations?locale={$locale}")
                ->assertOk()
                ->assertJsonCount(6, 'data')
                ->assertJsonPath('meta.total', 6);

            foreach ($destinations as $slug) {
                $this->getJson("/api/v1/destinations/{$slug}?locale={$locale}")
                    ->assertOk()
                    ->assertJsonPath('slug', $slug)
                    ->assertJsonStructure(['title', 'description']);
            }

            $this->getJson("/api/v1/services?locale={$locale}")
                ->assertOk()
                ->assertJsonCount(4);

            $this->getJson("/api/v1/posts?locale={$locale}")
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('meta.total', 3);

            foreach ($posts as $slug) {
                $this->getJson("/api/v1/posts/{$slug}?locale={$locale}")
                    ->assertOk()
                    ->assertJsonPath('slug', $slug)
                    ->assertJsonStructure(['title', 'excerpt', 'content']);
            }

            foreach ($pages as $slug) {
                $this->getJson("/api/v1/pages/{$slug}?locale={$locale}")
                    ->assertOk()
                    ->assertJsonPath('slug', $slug)
                    ->assertJsonStructure(['title', 'content']);
            }
        }

        $this->assertNotSame($localizedTitles['hy'], $localizedTitles['ru']);
        $this->assertNotSame($localizedTitles['hy'], $localizedTitles['en']);
        $this->assertNotSame($localizedDurations['hy'], $localizedDurations['ru']);
        $this->assertNotSame($localizedDurations['hy'], $localizedDurations['en']);
    }

    public function test_public_filters_pagination_fallbacks_and_missing_records_are_safe(): void
    {
        foreach (['group', 'private', 'package'] as $type) {
            $response = $this->getJson("/api/v1/tours?type={$type}&locale=en")->assertOk();

            foreach ($response->json('data') as $tour) {
                $this->assertSame($type, $tour['type']);
            }
        }

        foreach (['domestic', 'international'] as $scope) {
            $response = $this->getJson("/api/v1/tours?travel_scope={$scope}&locale=en&per_page=48")->assertOk();

            $this->assertNotEmpty($response->json('data'));
            foreach ($response->json('data') as $tour) {
                $this->assertSame($scope, $tour['travel_scope']);
            }
        }

        foreach (['accommodation', 'transport', 'events', 'custom'] as $type) {
            $response = $this->getJson("/api/v1/services?type={$type}&locale=en")
                ->assertOk()
                ->assertJsonCount(1);

            $this->assertSame($type, $response->json('0.type'));
        }

        $this->getJson('/api/v1/tours?featured=1&locale=en')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->getJson('/api/v1/tours?per_page=-1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/tours?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 48);

        $fallback = $this->getJson('/api/v1/home?locale=invalid')->assertOk()->json('settings.hero_title');
        $armenian = $this->getJson('/api/v1/home?locale=hy')->assertOk()->json('settings.hero_title');
        $this->assertSame($armenian, $fallback);

        $this->getJson('/api/v1/tours/missing-tour')->assertNotFound();
        $this->getJson('/api/v1/destinations/missing-destination')->assertNotFound();
        $this->getJson('/api/v1/posts/missing-post')->assertNotFound();
        $this->getJson('/api/v1/pages/missing-page')->assertNotFound();
    }

    public function test_booking_and_contact_requests_validate_and_persist(): void
    {
        $this->postJson('/api/v1/bookings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->postJson('/api/v1/bookings', [
            'name' => 'QA Guest',
            'email' => 'qa-booking@govista.test',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-09',
            'participants' => 0,
            'locale' => 'de',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date', 'participants', 'locale']);

        $booking = $this->postJson('/api/v1/bookings', [
            'type' => 'tour',
            'item_id' => 1,
            'item_title' => 'Garni & Geghard',
            'name' => 'QA Guest',
            'email' => 'qa-booking@govista.test',
            'phone' => '+374 99 000 000',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'participants' => 3,
            'locale' => 'en',
            'message' => 'Regression booking',
            'total_price' => 45000,
        ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'reference'])
            ->json();

        $this->assertStringStartsWith('GV-', $booking['reference']);
        $this->assertDatabaseHas('bookings', [
            'reference' => $booking['reference'],
            'email' => 'qa-booking@govista.test',
            'status' => 'new',
            'total_price' => null,
        ]);

        $this->postJson('/api/v1/contact', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        $this->postJson('/api/v1/contact', [
            'name' => 'QA Contact',
            'email' => 'not-an-email',
            'message' => 'Hello',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/contact', [
            'name' => 'QA Contact',
            'email' => 'qa-contact@govista.test',
            'phone' => '+374 10 000 000',
            'subject' => 'QA',
            'message' => 'Regression contact',
            'locale' => 'hy',
        ])->assertCreated()->assertJsonStructure(['message']);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'qa-contact@govista.test',
            'status' => 'new',
        ]);
    }

    public function test_admin_authentication_authorization_and_resource_indexes_work(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/content/tours')->assertUnauthorized();
        $this->postJson('/api/admin/upload')->assertUnauthorized();

        $this->postJson('/api/admin/login', [
            'email' => 'admin@govista.am',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $login = $this->postJson('/api/admin/login', [
            'email' => 'admin@govista.am',
            'password' => 'GoVista2026!',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'user'])
            ->json();

        $headers = ['Authorization' => 'Bearer '.$login['token']];

        $this->withHeaders($headers)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@govista.am');

        $this->withHeaders($headers)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonCount(4, 'stats')
            ->assertJsonStructure(['content', 'latest_bookings', 'latest_messages']);

        foreach ([
            'tours',
            'destinations',
            'services',
            'posts',
            'pages',
            'testimonials',
            'faqs',
            'settings',
            'bookings',
            'contact-messages',
        ] as $resource) {
            $this->withHeaders($headers)
                ->getJson("/api/admin/content/{$resource}?per_page=-1")
                ->assertOk()
                ->assertJsonPath('per_page', 1)
                ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
        }

        $this->withHeaders($headers)->getJson('/api/admin/content/unknown')->assertNotFound();

        $this->withHeaders($headers)->postJson('/api/admin/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_admin_users_cannot_authenticate_or_access_admin_routes(): void
    {
        $editor = User::factory()->create([
            'email' => 'editor@govista.test',
            'password' => bcrypt('EditorPassword123!'),
            'role' => 'editor',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $editor->email,
            'password' => 'EditorPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        Sanctum::actingAs($editor);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/content/tours')->assertForbidden();
        $this->postJson('/api/admin/upload')->assertForbidden();
    }

    public function test_security_headers_are_present_on_public_and_admin_responses(): void
    {
        $this->getJson('/api/v1/home?locale=en')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->get('/admin')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_can_create_read_update_search_and_delete_every_content_type(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@govista.am')->firstOrFail());

        $translations = fn (string $value) => [
            'hy' => "{$value} HY",
            'ru' => "{$value} RU",
            'en' => "{$value} EN",
        ];

        $resources = [
            'tours' => [
                'slug' => 'qa-tour',
                'travel_scope' => 'domestic',
                'type' => 'private',
                'title' => $translations('QA tour'),
                'description' => $translations('QA description'),
                'price' => 10000,
                'currency' => 'AMD',
                'active' => true,
                'featured' => false,
            ],
            'destinations' => [
                'slug' => 'qa-destination',
                'title' => $translations('QA destination'),
                'description' => $translations('QA description'),
                'active' => true,
            ],
            'services' => [
                'slug' => 'qa-service',
                'type' => 'custom',
                'title' => $translations('QA service'),
                'description' => $translations('QA description'),
                'currency' => 'AMD',
                'active' => true,
            ],
            'posts' => [
                'slug' => 'qa-post',
                'category' => 'qa',
                'title' => $translations('QA post'),
                'excerpt' => $translations('QA excerpt'),
                'content' => $translations('QA content'),
                'reading_time' => 3,
                'published_at' => '2026-07-27 12:00:00',
                'active' => true,
            ],
            'pages' => [
                'slug' => 'qa-page',
                'title' => $translations('QA page'),
                'content' => $translations('QA content'),
                'active' => true,
            ],
            'testimonials' => [
                'name' => 'QA Traveler',
                'country' => $translations('QA country'),
                'message' => $translations('QA message'),
                'rating' => 5,
                'active' => true,
            ],
            'faqs' => [
                'question' => $translations('QA question'),
                'answer' => $translations('QA answer'),
                'category' => 'qa',
                'active' => true,
            ],
            'settings' => [
                'group' => 'qa',
                'key' => 'qa_setting',
                'value' => $translations('QA value'),
                'type' => 'text',
            ],
        ];

        foreach ($resources as $resource => $payload) {
            $created = $this->postJson("/api/admin/content/{$resource}", $payload)
                ->assertCreated()
                ->assertJsonStructure(['id'])
                ->json();

            $id = $created['id'];

            $this->getJson("/api/admin/content/{$resource}/{$id}")
                ->assertOk()
                ->assertJsonPath('id', $id);

            $updatedPayload = $payload;
            if (isset($updatedPayload['title'])) {
                $updatedPayload['title'] = $translations('QA updated');
            } elseif ($resource === 'testimonials') {
                $updatedPayload['name'] = 'QA Traveler Updated';
            } elseif ($resource === 'faqs') {
                $updatedPayload['question'] = $translations('QA updated');
            } else {
                $updatedPayload['value'] = $translations('QA updated');
            }

            $this->putJson("/api/admin/content/{$resource}/{$id}", $updatedPayload)
                ->assertOk()
                ->assertJsonPath('id', $id);

            $searchTerm = $payload['slug'] ?? $payload['key'] ?? $payload['name'] ?? 'QA';
            $this->getJson("/api/admin/content/{$resource}?search=".urlencode($searchTerm))
                ->assertOk()
                ->assertJsonFragment(['id' => $id]);

            $this->deleteJson("/api/admin/content/{$resource}/{$id}")
                ->assertOk()
                ->assertJsonStructure(['message']);

            $this->getJson("/api/admin/content/{$resource}/{$id}")->assertNotFound();
        }

        $this->postJson('/api/admin/content/tours', $resources['tours'])->assertCreated();
        $this->postJson('/api/admin/content/tours', $resources['tours'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_manage_leads_and_upload_images(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@govista.am')->firstOrFail());

        $this->postJson('/api/v1/bookings', [
            'name' => 'Admin QA Booking',
            'email' => 'admin-qa-booking@govista.test',
            'locale' => 'en',
        ])->assertCreated();

        $this->postJson('/api/v1/contact', [
            'name' => 'Admin QA Contact',
            'email' => 'admin-qa-contact@govista.test',
            'message' => 'Admin QA contact',
            'locale' => 'en',
        ])->assertCreated();

        $booking = Booking::where('email', 'admin-qa-booking@govista.test')->firstOrFail();
        $contact = ContactMessage::where('email', 'admin-qa-contact@govista.test')->firstOrFail();

        $this->getJson("/api/admin/content/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('status', 'new');

        $this->putJson("/api/admin/content/bookings/{$booking->id}", [
            'status' => 'confirmed',
            'message' => 'Confirmed by QA',
            'total_price' => 12345,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->getJson("/api/admin/content/contact-messages/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('status', 'new');

        $this->putJson("/api/admin/content/contact-messages/{$contact->id}", [
            'status' => 'replied',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'replied');

        $this->deleteJson("/api/admin/content/bookings/{$booking->id}")->assertOk();
        $this->deleteJson("/api/admin/content/contact-messages/{$contact->id}")->assertOk();
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
        $this->assertDatabaseMissing('contact_messages', ['id' => $contact->id]);

        Storage::fake('public');

        $upload = $this->post('/api/admin/upload', [
            'file' => UploadedFile::fake()->image('qa-cover.jpg', 1200, 630),
        ])
            ->assertCreated()
            ->assertJsonStructure(['path', 'url'])
            ->json();

        Storage::disk('public')->assertExists($upload['path']);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/admin/upload', [
                'file' => UploadedFile::fake()->create('qa.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }
}
