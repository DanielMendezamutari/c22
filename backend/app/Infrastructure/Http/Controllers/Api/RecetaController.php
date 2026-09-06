<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Recetas\GestionarRecetasUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class RecetaController extends Controller
{
    private GestionarRecetasUseCase $useCase;

    public function __construct(GestionarRecetasUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function listarTransformaciones(): JsonResponse
    {
        try {
            $recetas = $this->useCase->listarTransformaciones();
            return response()->json([
                'success' => true,
                'data' => $recetas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function guardarTransformacion(Request $request, ?int $id = null): JsonResponse
    {
        $request->validate([
            'insumo_origen_id' => 'required|integer|exists:productos,id',
            'producto_destino_id' => 'required|integer|exists:productos,id',
            'tarifa_comision' => 'required|numeric|min:0',
            'ratio_teorico' => 'required|numeric|min:0.01',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $receta = $this->useCase->guardarTransformacion($request->all(), $id);
            return response()->json([
                'success' => true,
                'message' => $id ? 'Receta actualizada exitosamente' : 'Receta creada exitosamente',
                'data' => $receta,
            ], $id ? 200 : 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarCombos(): JsonResponse
    {
        try {
            $combos = $this->useCase->listarCombos();
            return response()->json([
                'success' => true,
                'data' => $combos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function guardarCombo(Request $request, ?int $id = null): JsonResponse
    {
        $request->validate([
            'nombre_combo' => 'required|string|max:100',
            'producto_terminado_id' => 'required|integer|exists:productos,id',
            'unidades_producto' => 'required|integer|min:1',
            'precio_combo' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $combo = $this->useCase->guardarCombo($request->all(), $id);
            return response()->json([
                'success' => true,
                'message' => $id ? 'Combo actualizado exitosamente' : 'Combo creado exitosamente',
                'data' => $combo,
            ], $id ? 200 : 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
