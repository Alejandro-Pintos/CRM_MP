<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $query = Usuario::query();

            // Filtro por rol
            if ($request->filled('rol')) {
                $query->role($request->rol);
            }

            // Búsqueda por nombre o email
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $usuarios = $query->paginate($perPage);

            return UserResource::collection($usuarios);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $usuario = Usuario::create([
                'nombre' => $request->nombre,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Asignar roles si se proporcionaron
            if ($request->filled('roles')) {
                $usuario->syncRoles($request->roles);
            }

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => new UserResource($usuario),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(Usuario $usuario)
    {
        try {
            return new UserResource($usuario);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, Usuario $usuario): JsonResponse
    {
        try {
            $data = $request->only(['nombre', 'email']);

            // Solo actualizar password si se proporciona
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $usuario->update($data);

            // Actualizar roles si se proporcionaron
            if ($request->filled('roles')) {
                $usuario->syncRoles($request->roles);
            }

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'data' => new UserResource($usuario),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Usuario $usuario): JsonResponse
    {
        try {
            // Prevenir que el usuario se elimine a sí mismo
            if ($usuario->id === auth()->id()) {
                return response()->json([
                    'message' => 'No puedes eliminar tu propia cuenta',
                ], 403);
            }

            $usuario->delete();

            return response()->json([
                'message' => 'Usuario eliminado exitosamente',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
