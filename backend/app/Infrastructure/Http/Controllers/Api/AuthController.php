<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Auth\LoginPinUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AuthController extends Controller
{
    private LoginPinUseCase $loginUseCase;

    public function __construct(LoginPinUseCase $loginUseCase)
    {
        $this->loginUseCase = $loginUseCase;
    }

    public function loginPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|size:4',
            'sucursal_id' => 'nullable|integer',
            'usuario_id' => 'nullable|integer',
        ]);

        try {
            $resultado = $this->loginUseCase->ejecutar(
                isset($validated['sucursal_id']) ? (int) $validated['sucursal_id'] : null,
                isset($validated['usuario_id']) ? (int) $validated['usuario_id'] : null,
                $validated['pin']
            );

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    public function listarSucursales(): JsonResponse
    {
        $sucursales = Sucursal::where('activo', true)->get(['id', 'nombre', 'codigo']);

        return response()->json([
            'success' => true,
            'data' => $sucursales,
        ]);
    }

    public function listarSucursalesYUsuarios(): JsonResponse
    {
        $sucursales = Sucursal::where('activo', true)->get(['id', 'nombre', 'codigo']);
        $usuarios = Usuario::where('activo', true)->get(['id', 'nombre', 'apellido', 'rol', 'sucursal_actual_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'sucursales' => $sucursales,
                'usuarios' => $usuarios,
            ],
        ]);
    }
}
