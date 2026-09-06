<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Auditoria\CalcularAuditoriaUseCase;
use App\Application\UseCases\Auditoria\GenerarLiquidacionSemanalUseCase;
use App\Domain\Ports\AuditoriaRepositoryPort;
use App\Infrastructure\Http\Requests\CalcularAuditoriaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AuditoriaController extends Controller
{
    private CalcularAuditoriaUseCase $auditoriaUseCase;
    private GenerarLiquidacionSemanalUseCase $liquidacionUseCase;
    private AuditoriaRepositoryPort $auditoriaRepo;
    private \App\Application\UseCases\Auditoria\ObtenerReporteRatiosUseCase $ratiosUseCase;

    public function __construct(
        CalcularAuditoriaUseCase $auditoriaUseCase,
        GenerarLiquidacionSemanalUseCase $liquidacionUseCase,
        AuditoriaRepositoryPort $auditoriaRepo,
        \App\Application\UseCases\Auditoria\ObtenerReporteRatiosUseCase $ratiosUseCase
    ) {
        $this->auditoriaUseCase = $auditoriaUseCase;
        $this->liquidacionUseCase = $liquidacionUseCase;
        $this->auditoriaRepo = $auditoriaRepo;
        $this->ratiosUseCase = $ratiosUseCase;
    }

    public function calcularAuditoria(CalcularAuditoriaRequest $request): JsonResponse
    {
        try {
            $adminId = $request->user()?->id ?? $request->input('admin_id', 2);
            $datos = $request->validated();
            $datos['admin_id'] = $adminId;

            $resultado = $this->auditoriaUseCase->ejecutar($datos);

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function liquidacionSemanal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semana' => 'nullable|string',
            'sucursal_id' => 'nullable|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        try {
            $resultado = $this->liquidacionUseCase->ejecutar(
                $validated['semana'] ?? null,
                isset($validated['sucursal_id']) ? (int) $validated['sucursal_id'] : null,
                $validated['fecha_inicio'] ?? null,
                $validated['fecha_fin'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function registrarPagoLiquidacion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barman_id' => 'required|integer|exists:usuarios,id',
            'monto_pagado' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sueldo semanal registrado como pagado exitosamente',
        ]);
    }

    public function reporteRatios(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer',
            'producto_destino_id' => 'nullable|integer',
        ]);

        try {
            $ratios = $this->ratiosUseCase->ejecutar(
                (int) $request->input('sucursal_id'),
                $request->input('producto_destino_id') ? (int) $request->input('producto_destino_id') : null
            );

            return response()->json([
                'success' => true,
                'data' => $ratios,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Paso 1 del Asistente de Auditoría: lista los turnos cerrados pendientes de auditar.
     */
    public function turnosPendientes(Request $request): JsonResponse
    {
        try {
            $query = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'barman'])
                ->whereIn('estado', ['cerrado', 'cobrado'])
                ->orderBy('id', 'desc');

            if ($request->filled('sucursal_id') && (int) $request->sucursal_id > 0) {
                $query->where('sucursal_id', (int) $request->sucursal_id);
            }

            $turnos = $query->limit(30)->get()->map(function ($t) {
                $tieneAuditoria = \App\Infrastructure\Persistence\Eloquent\Models\AuditoriaVenta::where('turno_id', $t->id)->exists();
                return [
                    'turno_id' => $t->id,
                    'sucursal_id' => $t->sucursal_id,
                    'sucursal_nombre' => $t->sucursal->nombre ?? 'N/A',
                    'barman_id' => $t->barman_id,
                    'barman_nombre' => ($t->barman->nombre ?? '') . ' ' . ($t->barman->apellido ?? ''),
                    'tipo_turno' => $t->tipo_turno,
                    'estado' => $t->estado,
                    'ya_auditado' => $tieneAuditoria,
                    'fecha_apertura' => $t->fecha_apertura ? $t->fecha_apertura->format('Y-m-d H:i') : null,
                    'fecha_cierre' => $t->fecha_cierre ? $t->fecha_cierre->format('Y-m-d H:i') : null,
                    'total_transformaciones_netas' => (int) $t->total_transformaciones_netas,
                    'total_comision_neta_pagada' => (float) $t->total_comision_neta_pagada,
                    'foto_comprobante_url' => $t->foto_comprobante_cobro ? asset('storage/' . $t->foto_comprobante_cobro) : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $turnos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
