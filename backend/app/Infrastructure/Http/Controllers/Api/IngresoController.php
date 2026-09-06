<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Inventario\RegistrarIngresoUseCase;
use App\Infrastructure\Http\Requests\IngresoMercaderiaRequest;
use Exception;
use Illuminate\Http\JsonResponse;

class IngresoController
{
    private RegistrarIngresoUseCase $useCase;

    public function __construct(RegistrarIngresoUseCase $useCase)
    {
        $this->useCase = $useCase;
    }

    public function registrarIngreso(IngresoMercaderiaRequest $request): JsonResponse
    {
        try {
            $archivo = $request->file('foto') ?? $request->input('foto_base64');
            $resultado = $this->useCase->ejecutar($request->validated(), $archivo);

            return response()->json([
                'success' => true,
                'data' => [
                    'movimiento_id' => $resultado['movimiento_id'] ?? null,
                    'foto_url' => isset($resultado['foto_path']) ? asset('storage/' . $resultado['foto_path']) : null,
                    'foto_path' => $resultado['foto_path'] ?? null,
                    'cantidad_agregada' => $resultado['cantidad_ingresada'] ?? null,
                    'mensaje' => $resultado['mensaje'] ?? 'Ingreso registrado',
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
