<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\Compra;
use App\Infrastructure\Persistence\Eloquent\Models\CompraDetalle;
use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CompraController extends Controller
{
    /**
     * Lista el historial de compras/abastecimientos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Compra::with(['detalles.producto', 'sucursal', 'usuario'])
            ->orderBy('id', 'desc');

        if ($request->filled('sucursal_id') && (int) $request->sucursal_id > 0) {
            $query->where('sucursal_id', (int) $request->sucursal_id);
        }

        $compras = $query->limit(50)->get()->map(function (Compra $c) {
            return [
                'id' => $c->id,
                'sucursal_id' => $c->sucursal_id,
                'sucursal_nombre' => $c->sucursal->nombre ?? 'N/A',
                'usuario_nombre' => ($c->usuario->nombre ?? '') . ' ' . ($c->usuario->apellido ?? ''),
                'proveedor' => $c->proveedor,
                'numero_nota_factura' => $c->numero_nota_factura,
                'foto_comprobante_url' => $c->foto_comprobante ? asset('storage/' . $c->foto_comprobante) : null,
                'total_costo_estimado' => (float) $c->total_costo_estimado,
                'total_items' => $c->detalles->count(),
                'total_unidades' => (float) $c->detalles->sum('cantidad'),
                'observaciones' => $c->observaciones,
                'fecha_compra' => $c->fecha_compra->toDateTimeString(),
                'detalles' => $c->detalles->map(function (CompraDetalle $d) {
                    return [
                        'id' => $d->id,
                        'producto_id' => $d->producto_id,
                        'producto_nombre' => $d->producto->nombre ?? 'N/A',
                        'tipo_producto' => $d->producto->tipo_producto ?? 'insumo',
                        'cantidad' => (float) $d->cantidad,
                        'costo_unitario' => (float) $d->costo_unitario,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $compras,
        ]);
    }

    /**
     * Registra una compra multi-producto de proveedor con foto obligatoria y actualiza inventario.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'proveedor' => 'required|string|max:150',
            'numero_nota_factura' => 'nullable|string|max:50',
            'numero_factura_nota' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|integer|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.costo_unitario' => 'nullable|numeric|min:0',
            'total_costo_estimado' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        $numeroNota = $request->input('numero_nota_factura') 
            ?? $request->input('numero_factura_nota') 
            ?? $request->input('numero_factura') 
            ?? 'S/N';

        // Procesar imagen obligatoria (file o base64, tolerante a varias claves)
        $fotoPath = null;
        $fotoField = null;
        foreach (['foto_comprobante', 'foto_factura', 'foto', 'comprobante'] as $f) {
            if ($request->hasFile($f) || $request->filled($f)) {
                $fotoField = $f;
                break;
            }
        }

        if ($fotoField && $request->hasFile($fotoField)) {
            $fotoPath = $request->file($fotoField)->store('compras', 'public');
        } elseif ($fotoField && $request->filled($fotoField)) {
            $base64 = $request->input($fotoField);
            if (str_starts_with($base64, 'data:image')) {
                $base64 = explode(',', $base64)[1] ?? $base64;
            }
            $decoded = base64_decode($base64);
            $fileName = 'compras/factura_' . time() . '_' . Str::random(6) . '.jpg';
            Storage::disk('public')->put($fileName, $decoded);
            $fotoPath = $fileName;
        }

        if (!$fotoPath) {
            return response()->json([
                'success' => false,
                'error' => 'La fotografía de la factura o nota de remisión es obligatoria para respaldar el ingreso de mercadería.',
            ], 422);
        }

        try {
            $resultado = DB::transaction(function () use ($request, $fotoPath, $numeroNota) {
                $sucursalId = (int) $request->sucursal_id;
                $usuarioId = auth()->id() ?? $request->input('usuario_id', 1);

                // Buscar turno activo en la sucursal si existe
                $turnoActivo = Turno::where('sucursal_id', $sucursalId)
                    ->where('estado', 'abierto')
                    ->latest()
                    ->first();

                // Calcular total costo si no viene
                $totalCosto = (float) $request->input('total_costo_estimado', 0.00);
                if ($totalCosto <= 0) {
                    foreach ($request->items as $it) {
                        $totalCosto += ((float) $it['cantidad']) * ((float) ($it['costo_unitario'] ?? 0.00));
                    }
                }

                $compra = Compra::create([
                    'sucursal_id' => $sucursalId,
                    'usuario_id' => $usuarioId,
                    'proveedor' => $request->proveedor,
                    'numero_nota_factura' => $numeroNota,
                    'foto_comprobante' => $fotoPath,
                    'total_costo_estimado' => $totalCosto,
                    'observaciones' => $request->observaciones,
                    'fecha_compra' => now(),
                ]);

                $totalUnidades = 0;
                foreach ($request->items as $item) {
                    $productoId = (int) $item['producto_id'];
                    $cantidad = (float) $item['cantidad'];
                    $costoUnitario = (float) ($item['costo_unitario'] ?? 0.00);
                    $totalUnidades += $cantidad;

                    CompraDetalle::create([
                        'compra_id' => $compra->id,
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'costo_unitario' => $costoUnitario,
                    ]);

                    // Asentar movimiento en bitácora de inventario
                    MovimientoInventario::create([
                        'uuid_local' => (string) Str::uuid(),
                        'turno_id' => $turnoActivo ? $turnoActivo->id : 1,
                        'sucursal_id' => $sucursalId,
                        'producto_id' => $productoId,
                        'tipo_movimiento' => 'ingreso',
                        'cantidad' => $cantidad,
                        'foto_path' => $fotoPath,
                        'observaciones' => 'Compra ' . $compra->proveedor . ($compra->numero_nota_factura ? ' (Nota: ' . $compra->numero_nota_factura . ')' : ''),
                        'fecha_movimiento' => now(),
                    ]);
                }

                return [
                    'compra_id' => $compra->id,
                    'proveedor' => $compra->proveedor,
                    'numero_nota_factura' => $compra->numero_nota_factura,
                    'total_items' => count($request->items),
                    'total_unidades' => $totalUnidades,
                    'total_costo_estimado' => $totalCosto,
                    'foto_comprobante_url' => asset('storage/' . $fotoPath),
                    'fecha_compra' => $compra->fecha_compra->toDateTimeString(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada e inventario actualizado exitosamente.',
                'data' => $resultado,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar la compra: ' . $e->getMessage(),
            ], 400);
        }
    }
}
