<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct()
    {
        // Protege estas rutas con el guard api; login queda libre
        $this->middleware('auth:api')->only(['me', 'logout', 'refresh']);
    }

    // POST /api/login
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$token = auth('api')->attempt($validated)) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->respondWithToken($token);
    }

    // POST /api/v1/me  (según tu route:list)
    public function me(Request $request)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $user->toArray();

        //  usa Spatie\Permission, agrega roles/permisos si existen
        if (method_exists($user, 'getRoleNames')) {
            $payload['roles'] = $user->getRoleNames();
        }
        if (method_exists($user, 'getAllPermissions')) {
            $payload['permissions'] = $user->getAllPermissions()->pluck('name');
        }

        return response()->json($payload, Response::HTTP_OK);
    }

    // POST /api/v1/logout
    public function logout()
    {
        try {
            auth('api')->logout();
        } catch (\Throwable $e) {
            // Token inválido o ya invalidado: devolvemos OK idempotente
        }

        return response()->json(['message' => 'Successfully logged out'], Response::HTTP_OK);
    }

    // POST /api/v1/refresh
    public function refresh()
    {
        try {
            $newToken = auth('api')->refresh();
            return $this->respondWithToken($newToken);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token refresh failed'], Response::HTTP_UNAUTHORIZED);
        }
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
