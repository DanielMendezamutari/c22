<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Inventario\EnviarTraspasoUseCase;
use App\Application\UseCases\Inventario\RecibirTraspasoUseCase;
use App\Domain\Ports\TraspasoRepositoryPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class TraspasoController extends Controller
{
    private EnviarTraspasoUseCase $enviarUseCase;
    private RecibirTraspasoUseCase $recibirUseCase;
    private TraspasoRepositoryPort $traspasoRepo;

    public function __construct(
        EnviarTraspasoUseCase $enviarUseCase,
        RecibirTraspasoUseCase $recibirUseCase,
        TraspasoRepositoryPort $traspasoRepo
    ) {
        $this->enviarUseCase = $enviarUseCase;
        $this->recibirUseCase = $recibirUseCase;
        $this->traspasoRepo = $traspasoRepo;
    }

    public function enviar(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_origen_id' => 'required|integer|exists:sucursales,id',
            'sucursal_destino_id' => 'required|integer|exists:sucursales,id|different:sucursal_origen_id',
            'producto_id' => 'required_without:items|nullable|integer|exists:productos,id',
            'cantidad' => 'required_without:items|nullable|numeric|min:0.01',
            'items' => 'required_without:producto_id|nullable|array|min:1',
            'items.*.producto_id' => 'required_with:items|integer|exists:productos,id',
            'items.*.cantidad' => 'required_with:items|numeric|min:0.01',
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $user = $request->user();
            $emisorId = $user ? $user->id : (int) $request->input('usuario_emisor_id', 1);

            $datos = $request->all();
            $datos['usuario_emisor_id'] = $emisorId;

            $resultado = $this->enviarUseCase->ejecutar($datos);

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function recibir(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'cantidad_recibida_conforme' => 'nullable|numeric|min:0',
            'cantidad_merma_transito' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'observaciones' => 'nullable|string|max:500',
            'foto_recepcion' => 'nullable|string',
        ]);

        try {
            $user = $request->user();
            $receptorId = $user ? $user->id : (int) $request->input('usuario_receptor_id', 1);

            $datos = $request->all();
            $datos['usuario_receptor_id'] = $receptorId;

            $resultado = $this->recibirUseCase->ejecutar($id, $datos);

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarPendientes(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_destino_id' => 'required|integer|exists:sucursales,id',
        ]);

        try {
            $pendientes = $this->traspasoRepo->listarPendientes((int) $request->input('sucursal_destino_id'));
            return response()->json([
                'success' => true,
                'data' => $pendientes,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Consulta el saldo físico disponible de productos en barra para validación en traspasos.
     */
    public function stockDisponible(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'producto_id' => 'nullable|integer|exists:productos,id',
        ]);

        try {
            $sucursalId = (int) $request->input('sucursal_id');
            $productoId = $request->input('producto_id') ? (int) $request->input('producto_id') : null;

            if ($productoId) {
                $stock = EnviarTraspasoUseCase::obtenerStockDisponible($sucursalId, $productoId);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'producto_id' => $productoId,
                        'stock_disponible' => $stock,
                    ],
                ]);
            }

            // Listar stock de todos los productos activos
            $productos = \App\Infrastructure\Persistence\Eloquent\Models\Producto::where('activo', true)->get();
            $stocks = $productos->map(function ($p) use ($sucursalId) {
                return [
                    'producto_id' => $p->id,
                    'producto_nombre' => $p->nombre,
                    'tipo_producto' => $p->tipo_producto,
                    'stock_disponible' => EnviarTraspasoUseCase::obtenerStockDisponible($sucursalId, $p->id),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stocks,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
