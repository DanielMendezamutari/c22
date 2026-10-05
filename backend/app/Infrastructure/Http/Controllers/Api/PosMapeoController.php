<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PosProductoMapeo;
use App\Infrastructure\Persistence\Eloquent\Models\PosTransaccion;
use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaCombo;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosMapeoController extends Controller
{
    /**
     * Listar mapeos de productos POS y catálogo de homologación.
     * Endpoint: GET /api/v1/pos/mapeo-productos
     */
    public function index(Request $request): JsonResponse
    {
        $sucursalId = $request->query('sucursal_id');

        $query = PosProductoMapeo::with(['sucursal', 'producto', 'combo'])
            ->orderBy('id', 'desc');

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        $mapeos = $query->get()->map(function ($m) {
            $esMapeado = ($m->c22_producto_id !== null || $m->c22_combo_id !== null);
            return [
                'id' => $m->id,
                'sucursal_id' => $m->sucursal_id,
                'sucursal_nombre' => $m->sucursal?->nombre,
                'pos_producto_id' => $m->pos_producto_id,
                'pos_nombre_producto' => $m->pos_nombre_producto,
                'c22_producto_id' => $m->c22_producto_id,
                'c22_producto_nombre' => $m->producto?->nombre,
                'c22_combo_id' => $m->c22_combo_id,
                'c22_combo_nombre' => $m->combo?->nombre_combo ?: $m->combo?->nombre,
                'activo' => $m->activo,
                'estado' => $esMapeado ? 'mapeado' : 'pendiente_mapeo',
                'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        // Catálogos disponibles para selector en pantalla
        $productosDisponibles = Producto::where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'unidad_medida']);

        $combosDisponibles = RecetaCombo::where('activo', true)
            ->orderBy('nombre_combo')
            ->get(['id', 'nombre_combo'])
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre_combo,
                ];
            });

        $sucursales = Sucursal::where('activo', true)
            ->get(['id', 'nombre', 'codigo', 'token_acceso'])
            ->map(function ($s) {
                if (!$s->token_acceso) {
                    $s->token_acceso = 'c22_' . \Illuminate\Support\Str::slug($s->codigo ?: $s->nombre, '_') . '_' . \Illuminate\Support\Str::random(16);
                    $s->save();
                }
                return $s;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'mapeos' => $mapeos,
                'catalogo_productos' => $productosDisponibles,
                'catalogo_combos' => $combosDisponibles,
                'sucursales' => $sucursales,
            ],
        ]);
    }

    /**
     * Guardar o actualizar la vinculación de un ítem POS a producto o combo de C22.
     * Endpoint: POST /api/v1/pos/mapeo-productos
     */
    public function guardarMapeo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'pos_producto_id' => 'required|string',
            'pos_nombre_producto' => 'nullable|string',
            'c22_producto_id' => 'nullable|integer|exists:productos,id',
            'c22_combo_id' => 'nullable|integer|exists:recetas_combos,id',
        ]);

        if (empty($validated['c22_producto_id']) && empty($validated['c22_combo_id'])) {
            return response()->json([
                'success' => false,
                'error' => 'Debe asociar al menos un Producto o un Combo de C22',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $mapeo = PosProductoMapeo::updateOrCreate(
                [
                    'sucursal_id' => $validated['sucursal_id'],
                    'pos_producto_id' => $validated['pos_producto_id'],
                ],
                [
                    'pos_nombre_producto' => $validated['pos_nombre_producto'] ?? $validated['pos_producto_id'],
                    'c22_producto_id' => $validated['c22_producto_id'] ?? null,
                    'c22_combo_id' => $validated['c22_combo_id'] ?? null,
                    'activo' => true,
                ]
            );

            // Actualizar transacciones históricas que estaban en estado 'pendiente_mapeo'
            PosTransaccion::where('sucursal_id', $validated['sucursal_id'])
                ->where('pos_producto_id', $validated['pos_producto_id'])
                ->update(['estado_mapeo' => 'mapeado']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mapeo de producto actualizado exitosamente.',
                'data' => $mapeo,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar el mapeo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
