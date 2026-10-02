<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Auth\LoginPinUseCase;
use App\Application\UseCases\Auth\LoginWebUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AuthController extends Controller
{
    private LoginPinUseCase $loginUseCase;
    private LoginWebUseCase $loginWebUseCase;

    public function __construct(LoginPinUseCase $loginUseCase, LoginWebUseCase $loginWebUseCase)
    {
        $this->loginUseCase = $loginUseCase;
        $this->loginWebUseCase = $loginWebUseCase;
    }

    public function loginWeb(Request $request): JsonResponse
    {
        // 1. Soporte de ingreso directo con PIN (Mismo PIN de la APK, ej: 9999 para Admin)
        $pin = $request->input('pin');
        if (!empty($pin)) {
            $request->merge(['pin' => (string) $pin]);
            return $this->loginPin($request);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        // Si en el campo email se introdujo un PIN de 4 dígitos (ej: 9999)
        if (!empty($email) && strlen(trim($email)) === 4 && ctype_digit(trim($email))) {
            $request->merge(['pin' => trim($email)]);
            return $this->loginPin($request);
        }

        // Si no se proporcionaron credenciales
        if (empty($email) || empty($password)) {
            return response()->json([
                'success' => false,
                'error' => 'Por favor ingresa tu PIN de 4 dígitos o tu correo y contraseña.',
            ], 422);
        }

        try {
            $resultado = $this->loginWebUseCase->ejecutar(
                (string) $email,
                (string) $password
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

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'No autenticado'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'rol' => $user->rol,
            ],
        ]);
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
