<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\UserProfileResource;

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
            return response()->json([
                'message' => 'Email o contraseña incorrectos'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Obtener el usuario autenticado
        $user = auth('api')->user();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => new UserProfileResource($user), // Incluir datos del usuario
        ]);
    }

    // POST /api/v1/me  (según tu route:list)
    public function me(Request $request)
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Token inválido o expirado'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Usar el Resource para formatear la respuesta
            return new UserProfileResource($user);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Token inválido o expirado',
                'error' => $e->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
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
