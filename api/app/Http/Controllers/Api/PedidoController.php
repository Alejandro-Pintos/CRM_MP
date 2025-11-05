<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Models\DetallePedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        
        $query = Pedido::with(['cliente', 'items.producto', 'venta'])
            ->orderByDesc('fecha_pedido');

        // Filtros
        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('desde')) {
            $query->whereDate('fecha_pedido', '>=', $request->desde);
        }

        if ($request->has('hasta')) {
            $query->whereDate('fecha_pedido', '<=', $request->hasta);
        }

        // Si per_page es 'all', devolver todos sin paginación
        if ($perPage === 'all') {
            return PedidoResource::collection($query->get());
        }

        return PedidoResource::collection($query->paginate($perPage));
    }

    public function store(StorePedidoRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            
            // Si no tiene fecha de pedido, usar la actual
            if (!isset($data['fecha_pedido'])) {
                $data['fecha_pedido'] = now();
            }

            // Si no tiene fecha de entrega aproximada, calcular 7 días después
            if (!isset($data['fecha_entrega_aprox'])) {
                $data['fecha_entrega_aprox'] = now()->addDays(7);
            }

            // Traducir el estado del clima si viene en inglés
            if (isset($data['clima_estado'])) {
                $data['clima_estado'] = $this->traducirEstadoClima($data['clima_estado']);
            }

            // Crear el pedido
            $items = $data['items'];
            unset($data['items']);

            $pedido = Pedido::create($data);

            // Crear los items del pedido
            foreach ($items as $item) {
                DetallePedido::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_compra' => $item['precio_compra'] ?? 0,
                    'precio_venta' => $item['precio_venta'] ?? 0,
                    'porcentaje_iva' => $item['porcentaje_iva'] ?? 0,
                    'precio_unitario' => $item['precio_unitario'],
                    'observaciones' => $item['observaciones'] ?? null,
                ]);
            }

            DB::commit();

            $pedido->load(['cliente', 'items.producto']);

            return (new PedidoResource($pedido))
                ->additional(['message' => 'Pedido creado exitosamente'])
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el pedido',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Pedido $pedido)
    {
        $pedido->load(['cliente', 'items.producto', 'venta']);
        return new PedidoResource($pedido);
    }

    public function update(Request $request, Pedido $pedido)
    {
        $data = $request->validate([
            'estado' => ['nullable', 'in:pendiente,en_proceso,entregado,cancelado'],
            'fecha_entrega_aprox' => ['nullable', 'date'],
            'direccion_entrega' => ['nullable', 'string'],
            'ciudad_entrega' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'venta_id' => ['nullable', 'integer', 'exists:ventas,id'],
        ]);

        $pedido->update($data);
        $pedido->load(['cliente', 'items.producto', 'venta']);

        return (new PedidoResource($pedido))
            ->additional(['message' => 'Pedido actualizado exitosamente']);
    }

    public function destroy(Pedido $pedido)
    {
        $pedido->delete();
        return response()->json(['message' => 'Pedido eliminado exitosamente'], 200);
    }

    /**
     * Obtener el clima para una ciudad
     */
    public function getClima(Request $request)
    {
        $apiKey = env('OPENWEATHER_API_KEY', 'demo');
        $dias = (int) $request->input('dias', 5); // Por defecto 5 días de pronóstico

        // Verificar si la API key está configurada
        if ($apiKey === 'demo' || $apiKey === 'your_api_key_here' || empty($apiKey)) {
            return response()->json([
                'message' => 'API de clima no configurada',
                'error' => 'La API key de OpenWeatherMap no está configurada. Por favor, configura OPENWEATHER_API_KEY en el archivo .env',
                'instructions' => 'Visita https://openweathermap.org/api para obtener una API key gratuita',
                'pronostico' => [],
                'clima_actual' => [
                    'clima_estado' => 'Desconocido',
                    'clima_temperatura' => null,
                    'clima_humedad' => null,
                    'clima_descripcion' => 'API no configurada',
                    'ciudad' => 'Ubicación detectada',
                ],
            ], 200);
        }

        try {
            $params = [
                'appid' => $apiKey,
                'lang' => 'es',
                'units' => 'metric',
                'cnt' => min($dias * 8, 40), // API da datos cada 3 horas, 8 por día, máx 40 (5 días)
            ];

            // Soportar tanto coordenadas como ciudad
            if ($request->has('lat') && $request->has('lon')) {
                $request->validate([
                    'lat' => 'required|numeric|between:-90,90',
                    'lon' => 'required|numeric|between:-180,180',
                ]);
                
                $params['lat'] = $request->lat;
                $params['lon'] = $request->lon;
            } else {
                $request->validate([
                    'ciudad' => 'required|string',
                ]);
                
                $params['q'] = $request->ciudad . ',AR';
            }

            // Obtener pronóstico extendido (hasta 5 días, cada 3 horas)
            $response = Http::timeout(10)->get("https://api.openweathermap.org/data/2.5/forecast", $params);

            if ($response->failed()) {
                $statusCode = $response->status();
                $errorMsg = 'Error desconocido';
                
                if ($statusCode === 401) {
                    $errorMsg = 'API key inválida. Verifica tu OPENWEATHER_API_KEY en el archivo .env';
                } elseif ($statusCode === 404) {
                    $errorMsg = 'Ubicación no encontrada';
                } elseif ($statusCode === 429) {
                    $errorMsg = 'Límite de llamadas excedido (máx. 1000/día en plan gratuito)';
                }

                return response()->json([
                    'message' => 'No se pudo obtener el pronóstico',
                    'error' => $errorMsg,
                    'status_code' => $statusCode,
                    'pronostico' => [],
                    'clima_actual' => [
                        'clima_estado' => 'Error',
                        'clima_temperatura' => null,
                        'clima_humedad' => null,
                        'clima_descripcion' => $errorMsg,
                        'ciudad' => null,
                    ],
                ], 200);
            }

            $data = $response->json();
            
            // Procesar pronóstico por día
            $pronosticoPorDia = [];
            $fechaActual = null;
            
            foreach ($data['list'] as $item) {
                $fecha = date('Y-m-d', $item['dt']);
                $hora = date('H:i', $item['dt']);
                
                if (!isset($pronosticoPorDia[$fecha])) {
                    $pronosticoPorDia[$fecha] = [
                        'fecha' => $fecha,
                        'fecha_formato' => date('d/m/Y', $item['dt']),
                        'dia_semana' => $this->getDiaSemana($item['dt']),
                        'temp_max' => $item['main']['temp_max'],
                        'temp_min' => $item['main']['temp_min'],
                        'temp_promedio' => $item['main']['temp'],
                        'humedad' => $item['main']['humidity'],
                        'estado' => $this->traducirEstadoClima($item['weather'][0]['main']),
                        'descripcion' => ucfirst($item['weather'][0]['description']),
                        'icono' => $item['weather'][0]['icon'],
                        'probabilidad_lluvia' => ($item['pop'] ?? 0) * 100,
                        'viento' => $item['wind']['speed'] ?? 0,
                        'detalles' => [],
                    ];
                } else {
                    // Actualizar máximos y mínimos
                    $pronosticoPorDia[$fecha]['temp_max'] = max($pronosticoPorDia[$fecha]['temp_max'], $item['main']['temp_max']);
                    $pronosticoPorDia[$fecha]['temp_min'] = min($pronosticoPorDia[$fecha]['temp_min'], $item['main']['temp_min']);
                }
                
                // Agregar detalle por hora
                $pronosticoPorDia[$fecha]['detalles'][] = [
                    'hora' => $hora,
                    'temperatura' => $item['main']['temp'],
                    'estado' => $item['weather'][0]['main'],
                    'descripcion' => $item['weather'][0]['description'],
                    'icono' => $item['weather'][0]['icon'],
                    'probabilidad_lluvia' => ($item['pop'] ?? 0) * 100,
                ];
            }

            // Clima actual (primer registro)
            $climaActual = $data['list'][0] ?? null;

            return response()->json([
                'clima_actual' => [
                    'clima_estado' => $this->traducirEstadoClima($climaActual['weather'][0]['main'] ?? null),
                    'clima_temperatura' => $climaActual['main']['temp'] ?? null,
                    'clima_humedad' => $climaActual['main']['humidity'] ?? null,
                    'clima_descripcion' => ucfirst($climaActual['weather'][0]['description'] ?? null),
                    'icono' => $climaActual['weather'][0]['icon'] ?? null,
                    'ciudad' => $data['city']['name'] ?? null,
                ],
                'pronostico' => array_values($pronosticoPorDia),
                'ciudad' => $data['city']['name'] ?? null,
                'coordenadas' => [
                    'lat' => $data['city']['coord']['lat'] ?? null,
                    'lon' => $data['city']['coord']['lon'] ?? null,
                ],
                'pronostico_json' => json_encode($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al consultar el pronóstico',
                'error' => $e->getMessage(),
                'pronostico' => [],
                'clima_actual' => [
                    'clima_estado' => 'Error',
                    'clima_temperatura' => null,
                    'clima_humedad' => null,
                    'clima_descripcion' => 'Error de conexión',
                    'ciudad' => null,
                ],
            ], 200);
        }
    }

    /**
     * Traducir estados del clima de inglés a español
     */
    private function traducirEstadoClima($estado)
    {
        $traducciones = [
            'Clear' => 'Despejado',
            'Clouds' => 'Nublado',
            'Rain' => 'Lluvia',
            'Drizzle' => 'Llovizna',
            'Thunderstorm' => 'Tormenta',
            'Snow' => 'Nieve',
            'Mist' => 'Neblina',
            'Smoke' => 'Humo',
            'Haze' => 'Bruma',
            'Dust' => 'Polvo',
            'Fog' => 'Niebla',
            'Sand' => 'Arena',
            'Ash' => 'Ceniza',
            'Squall' => 'Chubasco',
            'Tornado' => 'Tornado',
        ];

        return $traducciones[$estado] ?? $estado;
    }

    /**
     * Obtener día de la semana en español
     */
    private function getDiaSemana($timestamp)
    {
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return $dias[date('w', $timestamp)];
    }

    /**
     * Asociar pedido con una venta
     */
    public function asociarVenta(Request $request, Pedido $pedido)
    {
        $request->validate([
            'venta_id' => 'required|integer|exists:ventas,id',
        ]);

        $pedido->update([
            'venta_id' => $request->venta_id,
            'estado' => 'en_proceso',
        ]);

        $pedido->load(['cliente', 'items.producto', 'venta']);

        return (new PedidoResource($pedido))
            ->additional(['message' => 'Pedido asociado a la venta exitosamente']);
    }

    /**
     * Obtener pedidos pendientes (sin venta asociada)
     */
    public function pendientes(Request $request)
    {
        $query = Pedido::with(['cliente', 'items.producto'])
            ->whereNull('venta_id')
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->orderByDesc('fecha_pedido');

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        return PedidoResource::collection($query->get());
    }
}
