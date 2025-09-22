<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClientesController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:clientes.index')->only(['index','show']);
        $this->middleware('permission:clientes.store')->only(['store']);
        $this->middleware('permission:clientes.update')->only(['update']);
        $this->middleware('permission:clientes.destroy')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $q = $request->get('q');

        $query = Cliente::query();
        if ($q) {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                   ->orWhere('email',  'like', "%{$q}%");
            });
        }

        return ClienteResource::collection(
            $query->orderBy('nombre')->paginate(10)
        );
    }

    public function store(StoreClienteRequest $request)
    {
        $cliente = Cliente::create($request->validated());

        return (new ClienteResource($cliente))
            ->additional(['message' => 'Cliente creado'])
            ->response()
            ->setStatusCode(201)                                   // <- 201 Created
            ->header('Location', route('clientes.show', $cliente)); // <- opcional REST
    }

    public function show(Cliente $cliente)
    {
        return new ClienteResource($cliente);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        $cliente->update($request->validated());

        return (new ClienteResource($cliente))
            ->additional(['message' => 'Cliente actualizado']);
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return response()->noContent(); // <- 204 No Content
    }
}
