<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register(array $data): User
    {
        $user = User::create($data);
        $user->assignRole('collaborator');

        return $user;
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function login(User $user): void
    {
        Auth::login($user);
    }

    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     */
    public function attemptJwt(array $credentials): ?string
    {
        $token = Auth::guard('api')->attempt($credentials);

        return $token ?: null;
    }

    public function refreshJwt(): string
    {
        return Auth::guard('api')->refresh();
    }

    public function logoutJwt(): void
    {
        Auth::guard('api')->logout();
    }
}
