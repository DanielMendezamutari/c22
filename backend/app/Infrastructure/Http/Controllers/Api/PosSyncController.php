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

        // Buscar turno activo de la sucursal
        $turnoActivo = Turno::where('sucursal_id', $sucursalId)
            ->where('estado', 'abierto')
            ->latest()
            ->first();

        $turnoId = $turnoActivo ? $turnoActivo->id : null;

        $insertadas = 0;
        $mapeadas = 0;
        $pendientesMapeo = 0;

        DB::beginTransaction();
        try {
            foreach ($transacciones as $t) {
                $posProdId = trim($t['pos_producto_id']);
                $posProdNombre = trim($t['pos_nombre_producto']);

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
                        'turno_id' => $turnoId,
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
}
