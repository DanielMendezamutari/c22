<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Transformacion\RegistrarTransformacionUseCase;
use App\Application\UseCases\Transformacion\RegistrarBajaUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class TransformacionController extends Controller
{
    private RegistrarTransformacionUseCase $transformacionUseCase;
    private RegistrarBajaUseCase $bajaUseCase;

    public function __construct(
        RegistrarTransformacionUseCase $transformacionUseCase,
        RegistrarBajaUseCase $bajaUseCase
    ) {
        $this->transformacionUseCase = $transformacionUseCase;
        $this->bajaUseCase = $bajaUseCase;
    }

    public function registrarRelleno(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid_local' => 'required|string',
            'turno_id' => 'required|integer',
            'receta_id' => 'required|integer',
            'insumo_origen_id' => 'nullable|integer|exists:productos,id',
            'insumos_origen' => 'nullable|array',
            'insumos_origen.*.insumo_id' => 'required_with:insumos_origen|integer|exists:productos,id',
            'insumos_origen.*.cantidad' => 'required_with:insumos_origen|numeric|min:0.01',
            'cantidad_insumo' => 'nullable|numeric|min:0.01',
            'cantidad_producida' => 'required|integer|min:1',
            'cantidad_roturas' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $resultado = $this->transformacionUseCase->ejecutar($validated);
            return response()->json([
                'success' => true,
                'message' => 'Transformación registrada exitosamente',
                'data' => $resultado,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function registrarBaja(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data['uuid_local'])) {
            $data['uuid_local'] = (string) \Illuminate\Support\Str::uuid();
        }
        if (empty($data['observaciones']) && !empty($data['motivo'])) {
            $data['observaciones'] = $data['motivo'];
        }

        $validated = validator($data, [
            'uuid_local' => 'required|string',
            'turno_id' => 'required|integer',
            'producto_id' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.01',
            'foto_path' => 'nullable|string',
            'observaciones' => 'nullable|string|max:500',
        ])->validate();

        try {
            $resultado = $this->bajaUseCase->ejecutar($validated);
            return response()->json([
                'success' => true,
                'message' => 'Baja asentada exitosamente',
                'data' => $resultado,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
