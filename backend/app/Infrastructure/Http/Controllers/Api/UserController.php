<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Throwable;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::with('sucursalActual:id,nombre,codigo')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $usuarios,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'rol' => 'required|in:barman,garzon,admin',
            'pin' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'sueldo_base_semanal' => 'nullable|numeric|min:0',
            'modalidad_cobro' => 'nullable|in:diario,semanal',
            'sucursal_actual_id' => 'nullable|integer|exists:sucursales,id',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $modalidad = $validated['modalidad_cobro'] ?? ($validated['rol'] === 'barman' ? 'semanal' : 'diario');

            $usuario = Usuario::create([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'rol' => $validated['rol'],
                'pin_hash' => Hash::make($validated['pin']),
                'modalidad_cobro' => $modalidad,
                'sueldo_base_semanal' => $validated['sueldo_base_semanal'] ?? 0.00,
                'sucursal_actual_id' => $validated['sucursal_actual_id'] ?? null,
                'saldo_deudor_acumulado' => 0.00,
                'activo' => $validated['activo'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $usuario->load('sucursalActual:id,nombre,codigo'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al crear usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);
        if (!$usuario) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'rol' => 'sometimes|required|in:barman,garzon,admin',
            'sueldo_base_semanal' => 'nullable|numeric|min:0',
            'modalidad_cobro' => 'nullable|in:diario,semanal',
            'sucursal_actual_id' => 'nullable|integer|exists:sucursales,id',
            'activo' => 'sometimes|boolean',
        ]);

        try {
            $usuario->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => $usuario->load('sucursalActual:id,nombre,codigo'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar usuario: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cambiarPin(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);
        if (!$usuario) {
            return response()->json([
                'success' => false,
                'error' => 'Usuario no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'nuevo_pin' => 'required|string|size:4|regex:/^[0-9]{4}$/',
        ]);

        try {
            $usuario->update([
                'pin_hash' => Hash::make($validated['nuevo_pin']),
            ]);

            return response()->json([
                'success' => true,
                'message' => "PIN actualizado exitosamente para {$usuario->nombre} {$usuario->apellido}",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar PIN: ' . $e->getMessage(),
            ], 500);
        }
    }
}
