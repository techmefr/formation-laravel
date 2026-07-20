<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_lands_on_seances_from_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('seances.index'));
    }

    public function test_guest_cannot_reach_seances(): void
    {
        $this->get(route('seances.index'))->assertRedirect(route('login'));
    }

    public function test_login_succeeds_and_redirects_to_seances(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertRedirect(route('seances.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->from('/login')
            ->post('/login', ['email' => 'user@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_an_email(): void
    {
        $this->from('/login')
            ->post('/login', ['password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_register_creates_a_collaborator_and_logs_in(): void
    {
        $this->post('/register', [
            'name' => 'Nouveau',
            'email' => 'nouveau@example.com',
            'password' => 'Password1!secure',
            'password_confirmation' => 'Password1!secure',
        ])->assertRedirect(route('seances.index'));

        $user = User::where('email', 'nouveau@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('collaborator'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_rejects_a_weak_password(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Faible',
            'email' => 'faible@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'faible@example.com']);
    }

    public function test_logout_returns_to_guest(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect();

        $this->assertGuest();
    }
}
