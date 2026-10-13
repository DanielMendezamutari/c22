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
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'remote_jid' => 'required|string|max:100',
            'nombre_grupo' => 'required|string|max:150',
            'tipo_auditoria' => 'required|in:cierre_recaudacion,gastos_caja_chica,taxis_rotacion,general,control_recaudo_global',
            'es_global' => 'nullable|boolean',
            'activo' => 'boolean',
        ]);

        $esGlobal = (bool) ($request->input('es_global') || empty($validated['sucursal_id']));
        $sucursalId = $esGlobal ? null : ($validated['sucursal_id'] ?? null);

        $grupo = SucursalWhatsAppGrupo::updateOrCreate(
            ['remote_jid' => $validated['remote_jid']],
            [
                'sucursal_id' => $sucursalId,
                'nombre_grupo' => $validated['nombre_grupo'],
                'tipo_auditoria' => $validated['tipo_auditoria'],
                'es_global' => $esGlobal,
                'activo' => $validated['activo'] ?? true,
            ]
        );

        $grupo->load('sucursal:id,nombre,codigo');

        return response()->json([
            'success' => true,
            'message' => 'Grupo de WhatsApp vinculado exitosamente.',
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
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'nombre_grupo' => 'sometimes|string|max:150',
            'tipo_auditoria' => 'sometimes|in:cierre_recaudacion,gastos_caja_chica,taxis_rotacion,general,control_recaudo_global',
            'es_global' => 'nullable|boolean',
            'activo' => 'sometimes|boolean',
        ]);

        if (array_key_exists('sucursal_id', $validated)) {
            $esGlobal = (bool) ($request->input('es_global') || empty($validated['sucursal_id']));
            $validated['es_global'] = $esGlobal;
            $validated['sucursal_id'] = $esGlobal ? null : $validated['sucursal_id'];
        }

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
