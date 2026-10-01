<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AlertaDiscrepancia;
use App\Infrastructure\Persistence\Eloquent\Models\AdminDispositivo;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AlertaController extends Controller
{
    public function listarDiscrepancias(Request $request): JsonResponse
    {
        try {
            $query = AlertaDiscrepancia::with([
                'sucursal',
                'turnoSaliente.usuario',
                'turnoEntrante.usuario',
                'producto',
            ])->orderBy('id', 'desc');

            if ($request->boolean('solo_pendientes', true)) {
                $query->where('resuelto', false);
            }

            $alertas = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'total_pendientes' => AlertaDiscrepancia::where('resuelto', false)->count(),
                'data' => $alertas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function resolverDiscrepancia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $alerta = AlertaDiscrepancia::findOrFail($id);
            $alerta->resuelto = true;
            if ($request->filled('observaciones')) {
                $alerta->observaciones = $request->input('observaciones');
            }
            $alerta->save();

            return response()->json([
                'success' => true,
                'message' => 'Alerta de discrepancia marcada como resuelta.',
                'data' => $alerta,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function resolverAdmin(Request $request): ?Usuario
    {
        $user = auth('sanctum')->user() ?? $request->user();
        if (!$user && $request->filled('usuario_id')) {
            $user = Usuario::find((int) $request->usuario_id);
        }
        if (!$user) {
            $user = Usuario::where('rol', 'admin')->first();
        }
        return ($user && $user->rol === 'admin') ? $user : null;
    }

    public function registrarDispositivoMaestro(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:150',
            'nombre_dispositivo' => 'nullable|string|max:100',
            'fcm_token' => 'nullable|string|max:500',
            'usuario_id' => 'nullable|integer',
        ]);

        try {
            $user = $this->resolverAdmin($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo administradores pueden registrar dispositivos de alerta.',
                ], 403);
            }

            $dispositivo = AdminDispositivo::updateOrCreate(
                [
                    'usuario_id' => $user->id,
                    'device_id' => $request->input('device_id'),
                ],
                [
                    'nombre_dispositivo' => $request->input('nombre_dispositivo', 'Celular Personal Admin'),
                    'fcm_token' => $request->input('fcm_token', ''),
                    'activo' => true,
                    'ultimo_acceso' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo registrado como celular personal para alertas.',
                'data' => $dispositivo,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function desvincularDispositivo(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'nullable|string|max:150',
        ]);

        try {
            $deviceId = $request->input('device_id');
            if ($deviceId) {
                AdminDispositivo::where('device_id', $deviceId)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo desvinculado de alertas exitosamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarDispositivosMaestros(Request $request): JsonResponse
    {
        try {
            $user = $this->resolverAdmin($request);
            $userId = $user ? $user->id : 1;
            $dispositivos = AdminDispositivo::where('usuario_id', $userId)
                ->orderBy('ultimo_acceso', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $dispositivos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function aprobarProducto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'nombre_oficial' => 'required|string|max:150',
            'codigo_barra' => 'nullable|string|max:100',
            'tipo' => 'nullable|string|in:terminado,insumo,mixto',
            'unidad_medida' => 'nullable|string|max:50',
            'precio_venta' => 'nullable|numeric|min:0',
        ]);

        try {
            $alerta = AlertaDiscrepancia::findOrFail($id);

            // Crear el producto oficial en el catálogo maestro
            $producto = \App\Infrastructure\Persistence\Eloquent\Models\Producto::create([
                'nombre' => $request->input('nombre_oficial'),
                'codigo_barra' => $request->input('codigo_barra') ?? ('GEN-' . strtoupper(substr(md5(uniqid('', true)), 0, 8))),
                'tipo' => $request->input('tipo', 'terminado'),
                'unidad_medida' => $request->input('unidad_medida', 'unidad'),
                'precio_venta' => $request->input('precio_venta', 0.00),
                'activo' => true,
            ]);

            // Consolidar el corte de inventario provisional asociado
            if ($alerta->turno_entrante_id) {
                \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $alerta->turno_entrante_id)
                    ->where('es_provisional', true)
                    ->whereNull('producto_id')
                    ->update([
                        'producto_id' => $producto->id,
                        'es_provisional' => false,
                        'nombre_provisional' => null,
                    ]);
            }

            // Marcar alerta resuelta
            $alerta->update([
                'producto_id' => $producto->id,
                'resuelto' => true,
                'observaciones' => trim(($alerta->observaciones ?? '') . "\n[APROBADO]: Convertido en producto oficial '{$producto->nombre}' (ID: {$producto->id})."),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto provisional aprobado y consolidado en el catálogo oficial',
                'data' => [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'alerta_resuelta' => true,
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function unificarProducto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'producto_id_oficial' => 'required|integer|exists:productos,id',
        ]);

        try {
            $alerta = AlertaDiscrepancia::findOrFail($id);
            $productoOficial = \App\Infrastructure\Persistence\Eloquent\Models\Producto::findOrFail((int) $request->input('producto_id_oficial'));

            $unidadesTransferidas = (float) $alerta->stock_declarado;

            // Actualizar o fusionar corte provisional
            if ($alerta->turno_entrante_id) {
                $corteProvisional = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $alerta->turno_entrante_id)
                    ->where('es_provisional', true)
                    ->whereNull('producto_id')
                    ->first();

                if ($corteProvisional) {
                    $corteExistente = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $alerta->turno_entrante_id)
                        ->where('producto_id', $productoOficial->id)
                        ->where('tipo_corte', $corteProvisional->tipo_corte)
                        ->first();

                    if ($corteExistente) {
                        $corteExistente->cantidad += (float) $corteProvisional->cantidad;
                        $corteExistente->save();
                        $corteProvisional->delete();
                    } else {
                        $corteProvisional->update([
                            'producto_id' => $productoOficial->id,
                            'es_provisional' => false,
                            'nombre_provisional' => null,
                        ]);
                    }
                }
            }

            // Marcar alerta resuelta
            $alerta->update([
                'producto_id' => $productoOficial->id,
                'resuelto' => true,
                'observaciones' => trim(($alerta->observaciones ?? '') . "\n[UNIFICADO]: Transferido al producto oficial '{$productoOficial->nombre}' (ID: {$productoOficial->id})."),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conteo provisional transferido al producto oficial existente exitosamente',
                'data' => [
                    'producto_id_oficial' => $productoOficial->id,
                    'nombre_oficial' => $productoOficial->nombre,
                    'unidades_transferidas' => $unidadesTransferidas,
                    'alerta_resuelta' => true,
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
