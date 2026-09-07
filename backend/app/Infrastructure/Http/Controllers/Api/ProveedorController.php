<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class ProveedorController extends Controller
{
    /**
     * Listar proveedores con filtro opcional de búsqueda y estado activo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Proveedor::query();

        if ($request->has('activo')) {
            $activo = filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo !== null) {
                $query->where('activo', $activo);
            }
        }

        if ($request->filled('q')) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('contacto_nombre', 'like', "%{$search}%")
                  ->orWhere('nit_o_ci', 'like', "%{$search}%");
            });
        }

        $proveedores = $query->withCount('compras')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $proveedores,
        ]);
    }

    /**
     * Crear un nuevo proveedor comercial.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150|unique:proveedores,nombre',
            'contacto_nombre' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:50',
            'nit_o_ci' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $proveedor = Proveedor::create([
                'nombre' => trim($validated['nombre']),
                'contacto_nombre' => isset($validated['contacto_nombre']) ? trim($validated['contacto_nombre']) : null,
                'telefono' => isset($validated['telefono']) ? trim($validated['telefono']) : null,
                'nit_o_ci' => isset($validated['nit_o_ci']) ? trim($validated['nit_o_ci']) : null,
                'direccion' => isset($validated['direccion']) ? trim($validated['direccion']) : null,
                'activo' => $validated['activo'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proveedor registrado exitosamente',
                'data' => $proveedor,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar un proveedor específico.
     */
    public function show(int $id): JsonResponse
    {
        $proveedor = Proveedor::withCount('compras')->find($id);

        if (!$proveedor) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $proveedor,
        ]);
    }

    /**
     * Actualizar datos del proveedor.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => "required|string|max:150|unique:proveedores,nombre,{$id}",
            'contacto_nombre' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:50',
            'nit_o_ci' => 'nullable|string|max:30',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $proveedor->update([
                'nombre' => trim($validated['nombre']),
                'contacto_nombre' => isset($validated['contacto_nombre']) ? trim($validated['contacto_nombre']) : null,
                'telefono' => isset($validated['telefono']) ? trim($validated['telefono']) : null,
                'nit_o_ci' => isset($validated['nit_o_ci']) ? trim($validated['nit_o_ci']) : null,
                'direccion' => isset($validated['direccion']) ? trim($validated['direccion']) : null,
                'activo' => $validated['activo'] ?? $proveedor->activo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado exitosamente',
                'data' => $proveedor->fresh(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar proveedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alternar estado activo del proveedor o eliminar si no tiene compras.
     */
    public function destroy(int $id): JsonResponse
    {
        $proveedor = Proveedor::withCount('compras')->find($id);

        if (!$proveedor) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        if ($proveedor->compras_count > 0) {
            // Desactivar en lugar de eliminar físicamente para preservar integridad histórica
            $proveedor->update(['activo' => false]);
            return response()->json([
                'success' => true,
                'message' => 'Proveedor con compras asociadas desactivado correctamente para resguardar historial.',
                'data' => $proveedor,
            ]);
        }

        $proveedor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proveedor eliminado permanentemente.',
        ]);
    }

    /**
     * Toggle rápido de estado activo.
     */
    public function toggleActivo(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json([
                'success' => false,
                'message' => 'Proveedor no encontrado',
            ], 404);
        }

        $proveedor->update(['activo' => !$proveedor->activo]);

        return response()->json([
            'success' => true,
            'message' => 'Estado del proveedor actualizado',
            'data' => $proveedor,
        ]);
    }
}
