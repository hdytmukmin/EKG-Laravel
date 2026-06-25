<?php

namespace Tests\Feature;

use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_dashboard_requires_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_super_admin_can_access_user_management(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is not available in this environment.');
        }

        $user = User::query()->create([
            'name' => 'Test Super Admin',
            'email' => 'test-super-'.uniqid().'@ekg.local',
            'password' => 'password',
            'role' => 'super_admin',
        ]);

        $this->actingAs($user)
            ->get('/users')
            ->assertOk();
    }

    public function test_admin_puskesmas_cannot_access_user_management(): void
    {
        $user = new User([
            'name' => 'Test Admin Puskesmas',
            'email' => 'test-admin-'.uniqid().'@ekg.local',
            'password' => 'password',
            'role' => 'admin_puskesmas',
        ]);
        $user->id = 999999;

        $this->actingAs($user)
            ->get('/users')
            ->assertForbidden();
    }
}
