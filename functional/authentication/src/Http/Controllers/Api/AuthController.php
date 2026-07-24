<?php

namespace Functional\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Functional\Authentication\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $token = $this->auth->attemptJwt($credentials);

        if (! $token) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken($this->auth->refreshJwt());
    }

    public function logout(): JsonResponse
    {
        $this->auth->logoutJwt();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
        ]);
    }
}
