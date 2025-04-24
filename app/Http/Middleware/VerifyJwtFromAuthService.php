<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Closure;

class VerifyJwtFromAuthService
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token missing'], 401);
        }

        $response = Http::withToken($token)->get('http://localhost:8000/api/users/me');


        if (!$response->ok()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userData = $response->json()['data'] ?? null;

        if (!$userData || !isset($userData['id'])) {
            return response()->json(['error' => 'Invalid user data'], 401);
        }

        $request->merge(['user_data' => $userData]);

        return $next($request);
    }
}
