<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MotivoBaja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class MotivoBajaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $motivos = MotivoBaja::where('activo', true)
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $motivos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function indexAdmin(): JsonResponse
    {
        try {
            $motivos = MotivoBaja::orderBy('id', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $motivos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'descripcion' => 'required|string|max:150',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $motivo = MotivoBaja::create([
                'descripcion' => trim($request->input('descripcion')),
                'activo' => $request->boolean('activo', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Motivo de baja creado exitosamente',
                'data' => $motivo,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:150',
            'activo' => 'nullable|boolean',
        ]);

        try {
            $motivo = MotivoBaja::findOrFail($id);
            if ($request->has('descripcion')) {
                $motivo->descripcion = trim($request->input('descripcion'));
            }
            if ($request->has('activo')) {
                $motivo->activo = $request->boolean('activo');
            }
            $motivo->save();

            return response()->json([
                'success' => true,
                'message' => 'Motivo de baja actualizado exitosamente',
                'data' => $motivo,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $motivo = MotivoBaja::findOrFail($id);
            // Desactivación lógica (soft) para no romper auditorías pasadas
            $motivo->activo = false;
            $motivo->save();

            return response()->json([
                'success' => true,
                'message' => 'Motivo de baja desactivado exitosamente',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
