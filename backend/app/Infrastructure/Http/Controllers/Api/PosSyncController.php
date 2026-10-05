<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PosProductoMapeo;
use App\Infrastructure\Persistence\Eloquent\Models\PosTransaccion;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosSyncController extends Controller
{
    /**
     * Ingesta por lotes de transacciones de ventas y pagos enviadas por el Agente Windows.
     * Endpoint: POST /api/v1/sync/pos-transacciones
     * Autenticado por middleware: branch.token
     */
    public function ingestarTransacciones(Request $request): JsonResponse
    {
        // Asegurar sucursal_id desde atributo inyectado por VerifyBranchToken si no vino en body
        if (!$request->has('sucursal_id') && $request->attributes->has('sucursal')) {
            $request->merge(['sucursal_id' => $request->attributes->get('sucursal')->id]);
        }

        // Normalizar claves si el agente envió nombres alternativos
        $rawTransacciones = $request->input('transacciones', []);
        if (is_array($rawTransacciones)) {
            foreach ($rawTransacciones as &$t) {
                if (isset($t['pos_transaccion_id']) && !isset($t['pos_detalle_id'])) {
                    $t['pos_detalle_id'] = (string)$t['pos_transaccion_id'];
                }
                if (isset($t['nombre_producto_pos']) && !isset($t['pos_nombre_producto'])) {
                    $t['pos_nombre_producto'] = (string)$t['nombre_producto_pos'];
                }
            }
            $request->merge(['transacciones' => $rawTransacciones]);
        }

        $validated = $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'fecha_envio' => 'nullable|date',
            'transacciones' => 'required|array',
            'transacciones.*.pos_detalle_id' => 'required|string',
            'transacciones.*.pos_cuenta_id' => 'required|string',
            'transacciones.*.fecha_hora' => 'required|date',
            'transacciones.*.pos_producto_id' => 'required|string',
            'transacciones.*.pos_nombre_producto' => 'required|string',
            'transacciones.*.cantidad' => 'required|numeric',
            'transacciones.*.precio_unitario' => 'required|numeric',
            'transacciones.*.subtotal' => 'required|numeric',
            'transacciones.*.metodo_pago' => 'nullable|string',
        ]);

        $sucursalId = (int)$validated['sucursal_id'];
        $transacciones = $validated['transacciones'];

        // Buscar turnos recientes de la sucursal para asociar turno_id automáticamente por fecha o estado
        $turnosRecientes = Turno::where('sucursal_id', $sucursalId)
            ->orderByDesc('fecha_apertura')
            ->take(15)
            ->get();

        $turnoActivo = $turnosRecientes->firstWhere('estado', 'abierto');
        $defaultTurnoId = $turnoActivo ? $turnoActivo->id : null;

        $insertadas = 0;
        $mapeadas = 0;
        $pendientesMapeo = 0;

        DB::beginTransaction();
        try {
            foreach ($transacciones as $t) {
                $posProdId = trim($t['pos_producto_id']);
                $posProdNombre = trim($t['pos_nombre_producto']);

                // Asociar al turno cuyo rango de fechas coincida, o al turno abierto
                $itemTurnoId = null;
                $tFecha = $t['fecha_hora'] ?? null;
                if ($tFecha) {
                    foreach ($turnosRecientes as $tr) {
                        $inicio = $tr->fecha_apertura ? $tr->fecha_apertura->format('Y-m-d H:i:s') : null;
                        $fin = $tr->fecha_cierre ? $tr->fecha_cierre->format('Y-m-d H:i:s') : null;
                        if ($inicio && $tFecha >= $inicio && (!$fin || $tFecha <= $fin)) {
                            $itemTurnoId = $tr->id;
                            break;
                        }
                    }
                }
                if (!$itemTurnoId) {
                    $itemTurnoId = $defaultTurnoId;
                }

                // 1. Resolver o registrar mapeo en pos_producto_mapeo
                $mapeo = PosProductoMapeo::firstOrCreate(
                    [
                        'sucursal_id' => $sucursalId,
                        'pos_producto_id' => $posProdId,
                    ],
                    [
                        'pos_nombre_producto' => $posProdNombre,
                        'c22_producto_id' => null,
                        'c22_combo_id' => null,
                        'activo' => true,
                    ]
                );

                $esMapeado = ($mapeo->c22_producto_id !== null || $mapeo->c22_combo_id !== null);
                $estadoMapeo = $esMapeado ? 'mapeado' : 'pendiente_mapeo';

                if ($esMapeado) {
                    $mapeadas++;
                } else {
                    $pendientesMapeo++;
                }

                // 2. Normalizar método de pago
                $metodoRaw = strtolower(trim($t['metodo_pago'] ?? 'efectivo'));
                $metodoNormalizado = match (true) {
                    str_contains($metodoRaw, 'qr') => 'qr',
                    str_contains($metodoRaw, 'tarj') || str_contains($metodoRaw, 'card') => 'tarjeta',
                    str_contains($metodoRaw, 'mix') => 'mixto',
                    default => 'efectivo',
                };

                // 3. Upsert idempotente de la transacción
                $posTrans = PosTransaccion::updateOrCreate(
                    [
                        'sucursal_id' => $sucursalId,
                        'pos_detalle_id' => (string)$t['pos_detalle_id'],
                    ],
                    [
                        'turno_id' => $itemTurnoId,
                        'pos_cuenta_id' => (string)$t['pos_cuenta_id'],
                        'fecha_hora' => $t['fecha_hora'],
                        'pos_producto_id' => $posProdId,
                        'pos_nombre_producto' => $posProdNombre,
                        'cantidad' => $t['cantidad'],
                        'precio_unitario' => $t['precio_unitario'],
                        'subtotal' => $t['subtotal'],
                        'metodo_pago' => $metodoNormalizado,
                        'estado_mapeo' => $estadoMapeo,
                    ]
                );

                if ($posTrans->wasRecentlyCreated) {
                    $insertadas++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'recibidas' => count($transacciones),
                    'insertadas' => $insertadas,
                    'mapeadas' => $mapeadas,
                    'pendientes_mapeo' => $pendientesMapeo,
                    'turno_vinculado_id' => $turnoId,
                    'mensaje' => 'Transacciones POS sincronizadas exitosamente.',
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en ingesta de transacciones POS: ' . $e->getMessage(), [
                'sucursal_id' => $sucursalId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar la ingesta POS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta de ventas y comandas POS en vivo para monitoreo en línea.
     * Endpoint: GET /api/v1/pos/transacciones-en-vivo
     */
    public function listarTransacciones(Request $request): JsonResponse
    {
        $sucursalId = $request->input('sucursal_id');
        $fecha = $request->input('fecha');
        $limite = (int)$request->input('limite', 150);

        $query = PosTransaccion::query();

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        if ($fecha) {
            $query->whereDate('fecha_hora', $fecha);
        }

        $transacciones = $query->latest('fecha_hora')->take($limite)->get();

        $totales = [
            'total_bs' => round((float)$transacciones->sum('subtotal'), 2),
            'efectivo_bs' => round((float)$transacciones->where('metodo_pago', 'efectivo')->sum('subtotal'), 2),
            'qr_bs' => round((float)$transacciones->where('metodo_pago', 'qr')->sum('subtotal'), 2),
            'tarjeta_bs' => round((float)$transacciones->where('metodo_pago', 'tarjeta')->sum('subtotal'), 2),
            'conteo' => $transacciones->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'totales' => $totales,
                'transacciones' => $transacciones,
            ],
        ]);
    }

    /**
     * Estado de conectividad de cada sucursal con su POS local.
     * Endpoint: GET /api/v1/pos/estado-casas
     */
    public function estadoCasas(): JsonResponse
    {
        $sucursales = \App\Infrastructure\Persistence\Eloquent\Models\Sucursal::where('activo', true)->get();

        $casas = $sucursales->map(function ($s) {
            $ultimaTransaccion = PosTransaccion::where('sucursal_id', $s->id)
                ->latest('fecha_hora')
                ->first();

            $totalHoy = (float)PosTransaccion::where('sucursal_id', $s->id)
                ->whereDate('fecha_hora', date('Y-m-d'))
                ->sum('subtotal');

            $transHoy = PosTransaccion::where('sucursal_id', $s->id)
                ->whereDate('fecha_hora', date('Y-m-d'))
                ->count();

            $conectada = $ultimaTransaccion !== null;

            return [
                'sucursal_id' => $s->id,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'conectada' => $conectada,
                'ultima_sincronizacion' => $ultimaTransaccion ? $ultimaTransaccion->fecha_hora : null,
                'ultima_sincronizacion_formateada' => $ultimaTransaccion ? date('d/m/Y H:i', strtotime($ultimaTransaccion->fecha_hora)) : 'Nunca',
                'ventas_hoy_bs' => round($totalHoy, 2),
                'transacciones_hoy' => $transHoy,
                'ultimo_producto' => $ultimaTransaccion ? $ultimaTransaccion->pos_nombre_producto : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $casas,
        ]);
    }
}
