<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class SucursalWhatsAppGrupoController extends Controller
{
    /**
     * Listar todos los grupos vinculados a sucursales.
     * GET /api/v1/sucursal-whatsapp-grupos
     */
    public function index(): JsonResponse
    {
        $grupos = SucursalWhatsAppGrupo::with('sucursal:id,nombre,codigo')
            ->orderBy('nombre_grupo')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $grupos,
        ]);
    }

    /**
     * Crear o actualizar la vinculación de un grupo a una sucursal.
     * POST /api/v1/sucursal-whatsapp-grupos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'remote_jid' => 'required|string|max:100',
            'nombre_grupo' => 'required|string|max:150',
            'tipo_auditoria' => 'required|in:cierre_recaudacion,gastos_caja_chica,taxis_rotacion,general',
            'activo' => 'boolean',
        ]);

        $grupo = SucursalWhatsAppGrupo::updateOrCreate(
            ['remote_jid' => $validated['remote_jid']],
            [
                'sucursal_id' => $validated['sucursal_id'],
                'nombre_grupo' => $validated['nombre_grupo'],
                'tipo_auditoria' => $validated['tipo_auditoria'],
                'activo' => $validated['activo'] ?? true,
            ]
        );

        $grupo->load('sucursal:id,nombre,codigo');

        return response()->json([
            'success' => true,
            'message' => 'Grupo de WhatsApp vinculado exitosamente a la sucursal.',
            'data' => $grupo,
        ], Response::HTTP_CREATED);
    }

    /**
     * Actualizar una vinculación existente.
     * PUT /api/v1/sucursal-whatsapp-grupos/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $grupo = SucursalWhatsAppGrupo::findOrFail($id);

        $validated = $request->validate([
            'sucursal_id' => 'sometimes|exists:sucursales,id',
            'nombre_grupo' => 'sometimes|string|max:150',
            'tipo_auditoria' => 'sometimes|in:cierre_recaudacion,gastos_caja_chica,taxis_rotacion,general',
            'activo' => 'sometimes|boolean',
        ]);

        $grupo->update($validated);
        $grupo->load('sucursal:id,nombre,codigo');

        return response()->json([
            'success' => true,
            'message' => 'Vinculación de grupo actualizada exitosamente.',
            'data' => $grupo,
        ]);
    }

    /**
     * Eliminar la vinculación de un grupo.
     * DELETE /api/v1/sucursal-whatsapp-grupos/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $grupo = SucursalWhatsAppGrupo::findOrFail($id);
        $grupo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vinculación de grupo eliminada exitosamente.',
        ]);
    }
}
