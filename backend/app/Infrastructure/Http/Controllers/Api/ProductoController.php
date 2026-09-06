<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Throwable;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query();

        if ($request->has('tipo') && !empty($request->input('tipo'))) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->has('activo')) {
            $activo = filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($activo !== null) {
                $query->where('activo', $activo);
            }
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', $term)
                  ->orWhere('codigo_barra', 'LIKE', $term);
            });
        }

        $productos = $query->orderBy('tipo')->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $productos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150|unique:productos,nombre',
            'codigo_barra' => 'nullable|string|max:50|unique:productos,codigo_barra',
            'tipo' => ['required', Rule::in(['insumo', 'terminado', 'ambos'])],
            'unidad_medida' => ['required', Rule::in(['unidad', 'fraccion_cuartos'])],
            'es_transformable' => 'boolean',
            'activo' => 'boolean',
        ]);

        try {
            $producto = Producto::create([
                'nombre' => trim($validated['nombre']),
                'codigo_barra' => isset($validated['codigo_barra']) ? trim($validated['codigo_barra']) : null,
                'tipo' => $validated['tipo'],
                'unidad_medida' => $validated['unidad_medida'],
                'es_transformable' => $validated['es_transformable'] ?? false,
                'activo' => $validated['activo'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente en el catálogo oficial',
                'data' => $producto,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al registrar el producto: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('productos', 'nombre')->ignore($id)],
            'codigo_barra' => ['nullable', 'string', 'max:50', Rule::unique('productos', 'codigo_barra')->ignore($id)],
            'tipo' => ['sometimes', 'required', Rule::in(['insumo', 'terminado', 'ambos'])],
            'unidad_medida' => ['sometimes', 'required', Rule::in(['unidad', 'fraccion_cuartos'])],
            'es_transformable' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        try {
            $producto->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar el producto: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function toggleActivo(int $id): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado.',
            ], 404);
        }

        $producto->activo = !$producto->activo;
        $producto->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del producto actualizado',
            'data' => $producto,
        ]);
    }
}
