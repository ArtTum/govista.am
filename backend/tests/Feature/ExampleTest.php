<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_root_redirects_to_the_admin_studio(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('GoVista Admin');
    }

    public function test_public_home_api_returns_localized_seed_content(): void
    {
        $this->getJson('/api/v1/home?locale=hy')
            ->assertOk()
            ->assertJsonPath('settings.site_name', 'GoVista')
            ->assertJsonCount(6, 'featured_tours')
            ->assertJsonPath('featured_tours.0.title', 'Գառնի, Գեղարդ և Քարերի սիմֆոնիա');
    }

    public function test_seeded_administrator_can_authenticate(): void
    {
        $this->postJson('/api/admin/login', [
            'email' => 'admin@govista.am',
            'password' => 'GoVista2026!',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_guest_can_submit_a_booking(): void
    {
        $this->postJson('/api/v1/bookings', [
            'type' => 'tour',
            'item_title' => 'Garni',
            'name' => 'Demo Guest',
            'email' => 'guest@example.com',
            'participants' => 2,
            'locale' => 'en',
        ])
            ->assertCreated()
            ->assertJsonStructure(['message', 'reference']);

        $this->assertDatabaseHas('bookings', ['email' => 'guest@example.com', 'status' => 'new']);
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $tables = [
            'users',
            'tours',
            'destinations',
            'services',
            'posts',
            'pages',
            'testimonials',
            'faqs',
            'settings',
        ];
        $before = collect($tables)->mapWithKeys(
            fn (string $table) => [$table => DB::table($table)->count()]
        );

        $this->seed();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} changed after a second seed.");
        }
    }
}
