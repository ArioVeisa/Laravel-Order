<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyJwtFromAuthService
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            Log::error('Token missing in request');
            return response()->json(['error' => 'Token missing'], 401);
        }

        // Debug log untuk melihat token
        Log::info('Verifying token with auth service', [
            'token' => $token,
            'auth_url' => env('AUTH_SERVICE_URL', 'http://auth-service:9000')
        ]);

        // Validasi token ke AuthService menggunakan environment variable
        $authServiceUrl = env('AUTH_SERVICE_URL', 'http://auth-service:9000');
        $response = Http::withToken($token)->get($authServiceUrl . '/api/users/me');

        // Debug log untuk melihat response dari auth service
        Log::info('Auth service response', [
            'status' => $response->status(),
            'body' => $response->json(),
            'headers' => $response->headers()
        ]);

        if (!$response->ok()) {
            Log::error('Token validation failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Ambil data user dari response
        $responseData = $response->json();
        
        // Cek format response yang benar
        if (!isset($responseData['user'])) {
            Log::error('Invalid response format from auth service', ['response' => $responseData]);
            return response()->json(['error' => 'Invalid response format from auth service'], 401);
        }

        $userData = $responseData['user'];
        
        // Pastikan data user memiliki id
        if (!isset($userData['id'])) {
            Log::error('User ID not found in auth service response', ['user_data' => $userData]);
            return response()->json(['error' => 'Invalid user data from auth service'], 401);
        }

        $request->merge(['user_data' => $userData]);

        return $next($request);
    }
}
