<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class SucursalController extends Controller
{
    /**
     * Listar sucursales con conteo de usuarios y turnos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sucursal::query();

        if ($request->has('activo')) {
            $activo = filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo !== null) {
                $query->where('activo', $activo);
            }
        }

        $sucursales = $query->withCount([
            'usuarios' => function ($q) {
                $q->where('activo', true);
            },
            'turnos',
        ])
        ->orderBy('nombre')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $sucursales,
        ]);
    }

    /**
     * Crear una nueva sucursal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:sucursales,nombre',
            'codigo' => 'required|string|max:20|unique:sucursales,codigo',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $sucursal = Sucursal::create([
                'nombre' => trim($validated['nombre']),
                'codigo' => strtoupper(trim($validated['codigo'])),
                'direccion' => isset($validated['direccion']) ? trim($validated['direccion']) : null,
                'activo' => $validated['activo'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal creada exitosamente',
                'data' => $sucursal,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la sucursal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una sucursal existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:sucursales,nombre,' . $id,
            'codigo' => 'required|string|max:20|unique:sucursales,codigo,' . $id,
            'direccion' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $sucursal->update([
                'nombre' => trim($validated['nombre']),
                'codigo' => strtoupper(trim($validated['codigo'])),
                'direccion' => isset($validated['direccion']) ? trim($validated['direccion']) : null,
                'activo' => isset($validated['activo']) ? (bool) $validated['activo'] : $sucursal->activo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal actualizada exitosamente',
                'data' => $sucursal,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la sucursal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suspender o reactivar una sucursal.
     */
    public function toggleActivo(int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada',
            ], 404);
        }

        try {
            $sucursal->activo = !$sucursal->activo;
            $sucursal->save();

            $estado = $sucursal->activo ? 'reactivada' : 'suspendida';

            return response()->json([
                'success' => true,
                'message' => "Sucursal {$sucursal->nombre} {$estado} exitosamente",
                'data' => $sucursal,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado de la sucursal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
