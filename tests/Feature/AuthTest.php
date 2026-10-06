<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('rooms.index'))->assertRedirect(route('login'));
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    public function test_first_registration_creates_owner(): void
    {
        $this->get(route('register'))->assertOk();

        $this->post(route('register'), [
            'name' => 'Pemilik',
            'email' => 'owner@example.com',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(UserRole::Owner, User::where('email', 'owner@example.com')->firstOrFail()->role);
        $this->assertAuthenticated();
    }

    public function test_registration_is_closed_after_first_user(): void
    {
        User::factory()->create();

        $this->get(route('register'))->assertNotFound();

        $this->post(route('register'), [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
    }

    public function test_viewer_cannot_access_settings_or_mutations(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($viewer)->post(route('rooms.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post(route('console.run'), ['input' => "room add 'X'"])->assertForbidden();

        // Melihat masih boleh.
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk();
        $this->actingAs($viewer)->get(route('rooms.index'))->assertOk();
    }

    public function test_manager_can_manage_rooms_but_not_settings(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->post(route('rooms.store'), ['name' => 'IT Team'])->assertRedirect();
        $this->actingAs($manager)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_user_add_command(): void
    {
        $this->artisan('user:add', ['email' => 'tim@example.com', '--role' => 'manager', '--password' => 'rahasia-123'])
            ->assertExitCode(0);

        $user = User::where('email', 'tim@example.com')->firstOrFail();
        $this->assertSame(UserRole::Manager, $user->role);

        $this->post(route('login'), ['email' => 'tim@example.com', 'password' => 'rahasia-123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->artisan('user:add', ['email' => 'tim@example.com'])->assertExitCode(1);
        $this->artisan('user:add', ['email' => 'y@example.com', '--role' => 'superadmin'])->assertExitCode(1);
    }
}
