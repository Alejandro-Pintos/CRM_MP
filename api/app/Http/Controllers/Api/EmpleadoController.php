<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmpleadoRequest;
use App\Http\Requests\UpdateEmpleadoRequest;
use App\Http\Resources\EmpleadoResource;
use App\Models\Empleado;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:empleados.index')->only(['index', 'show']);
        $this->middleware('permission:empleados.store')->only(['store']);
        $this->middleware('permission:empleados.update')->only(['update']);
        $this->middleware('permission:empleados.destroy')->only(['destroy']);
    }

    /**
     * Listar empleados con filtros
     * 
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $q = $request->string('q');
        $activo = $request->input('activo'); // true, false, null (todos)
        $perPage = $request->input('per_page', 15);

        $query = Empleado::query();

        // Filtro de búsqueda por nombre o documento
        if ($q->isNotEmpty()) {
            $query->where(function($qq) use ($q) {
                $qq->where('nombre_completo', 'like', '%'.$q.'%')
                   ->orWhere('documento', 'like', '%'.$q.'%');
            });
        }

        // Filtro por estado activo/inactivo
        if ($activo !== null) {
            $query->where('activo', filter_var($activo, FILTER_VALIDATE_BOOLEAN));
        }

        // Si per_page es 'all', devolver todos sin paginación
        if ($perPage === 'all') {
            return EmpleadoResource::collection(
                $query->orderBy('nombre_completo')->get()
            );
        }

        return EmpleadoResource::collection(
            $query->orderBy('nombre_completo')->paginate($perPage)
        );
    }

    /**
     * Crear un nuevo empleado
     * 
     * @param StoreEmpleadoRequest $request
     * @return EmpleadoResource
     */
    public function store(StoreEmpleadoRequest $request)
    {
        $empleado = Empleado::create($request->validated());

        return (new EmpleadoResource($empleado))
            ->additional(['message' => 'Empleado creado correctamente'])
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('empleados.show', $empleado));
    }

    /**
     * Ver detalle de un empleado
     * 
     * @param Empleado $empleado
     * @return EmpleadoResource
     */
    public function show(Empleado $empleado)
    {
        // Cargar relación de pagos para mostrar totales
        $empleado->load('pagos');
        
        return new EmpleadoResource($empleado);
    }

    /**
     * Actualizar un empleado existente
     * 
     * @param UpdateEmpleadoRequest $request
     * @param Empleado $empleado
     * @return EmpleadoResource
     */
    public function update(UpdateEmpleadoRequest $request, Empleado $empleado)
    {
        $empleado->update($request->validated());

        return (new EmpleadoResource($empleado))
            ->additional(['message' => 'Empleado actualizado correctamente']);
    }

    /**
     * Eliminar un empleado (soft delete)
     * 
     * @param Empleado $empleado
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Empleado $empleado)
    {
        $this->authorize('delete', $empleado);
        
        $empleado->delete();
        
        return response()->json([
            'message' => 'Empleado eliminado correctamente'
        ], 204);
    }
}
