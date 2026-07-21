<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_correct_credentials_returns_a_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'bearer');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Identifiants invalides.']);
    }

    public function test_login_with_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_without_token_is_unauthorized(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_with_invalid_token_is_unauthorized(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_me_with_valid_token_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_refresh_returns_a_new_working_token(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $refreshed = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/refresh')
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $newToken = $refreshed->json('access_token');
        $this->assertNotSame($token, $newToken);

        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_the_pre_refresh_token_is_blacklisted_but_still_authenticates(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);
        auth('api')->setToken($token);
        $payload = auth('api')->payload();

        auth('api')->setToken($token)->refresh();

        $this->assertTrue(app('tymon.jwt')->getBlacklist()->has($payload));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_logout_invalidates_the_token(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
