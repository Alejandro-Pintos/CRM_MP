<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Obtener perfil del usuario autenticado
     */
    public function show()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'No autenticado'], 401);
            }
            
            return new UserProfileResource($user);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener perfil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar datos básicos del perfil
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'No autenticado'], 401);
            }
            
            // Actualizar datos permitidos
            $data = $request->only(['nombre', 'email']);
            $user->update($data);
            
            return response()->json([
                'message' => 'Perfil actualizado exitosamente',
                'data' => new UserProfileResource($user),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar perfil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'No autenticado'], 401);
            }
            
            // Verificar contraseña actual
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta',
                    'errors' => [
                        'current_password' => ['La contraseña actual es incorrecta'],
                    ],
                ], 422);
            }
            
            // Actualizar contraseña
            $user->update([
                'password' => Hash::make($request->password),
            ]);
            
            return response()->json([
                'message' => 'Contraseña actualizada exitosamente',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar avatar del perfil
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json(['message' => 'No autenticado'], 401);
            }
            
            // Validar archivo
            $request->validate([
                'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'], // 2MB max
            ]);
            
            // Eliminar avatar anterior si existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            // Guardar nuevo avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            
            // Actualizar en base de datos
            $user->update(['avatar' => $path]);
            
            // Generar URL pública
            $avatarUrl = Storage::disk('public')->url($path);
            
            return response()->json([
                'message' => 'Avatar actualizado exitosamente',
                'data' => new UserProfileResource($user),
                'avatar_url' => $avatarUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar avatar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
