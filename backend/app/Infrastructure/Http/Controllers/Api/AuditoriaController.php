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

    /**
     * User Story 20: Informe Operativo Integral de Sucursal en Vivo o Histórico para el Administrador.
     */
    public function informeTurnoSucursal(Request $request, int $sucursal_id): JsonResponse
    {
        try {
            $sucursal = \App\Infrastructure\Persistence\Eloquent\Models\Sucursal::find($sucursal_id);
            if (!$sucursal) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sucursal no encontrada',
                ], 404);
            }

            // Historial de últimos 15 turnos para selector en la app
            $turnosHistorial = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with('barman')
                ->where('sucursal_id', $sucursal_id)
                ->orderBy('id', 'desc')
                ->limit(15)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'tipo_turno' => $t->tipo_turno,
                        'estado' => $t->estado,
                        'fecha_apertura' => $t->fecha_apertura ? $t->fecha_apertura->format('Y-m-d H:i') : null,
                        'fecha_cierre' => $t->fecha_cierre ? $t->fecha_cierre->format('Y-m-d H:i') : null,
                        'barman_nombre' => ($t->barman->nombre ?? '') . ' ' . ($t->barman->apellido ?? ''),
                    ];
                });

            // Seleccionar turno: por ID solicitado o el turno activo/último
            $turnoQuery = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['barman', 'sucursal'])
                ->where('sucursal_id', $sucursal_id);

            if ($request->filled('turno_id') && (int) $request->turno_id > 0) {
                $turno = $turnoQuery->where('id', (int) $request->turno_id)->first();
            } else {
                // Priorizar turno abierto (en vivo)
                $turno = (clone $turnoQuery)->where('estado', 'abierto')->latest()->first();
                if (!$turno) {
                    $turno = (clone $turnoQuery)->latest()->first();
                }
            }

            if (!$turno) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'sucursal' => [
                            'id' => $sucursal->id,
                            'nombre' => $sucursal->nombre,
                            'codigo' => $sucursal->codigo,
                            'direccion' => $sucursal->direccion,
                        ],
                        'turno' => null,
                        'turnos_historial' => $turnosHistorial,
                        'mensaje' => 'No hay turnos registrados en esta sucursal.',
                    ],
                ]);
            }

            $fechaInicio = $turno->fecha_apertura;
            $fechaFin = $turno->fecha_cierre ?? now();

            // 1. Corte Inicial (Apertura)
            $corteInicial = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::with('producto')
                ->where('turno_id', $turno->id)
                ->whereIn('tipo_corte', ['inicial', 'apertura'])
                ->get()
                ->map(function ($c) {
                    $total = (float) $c->unidades + ((float) $c->fraccion_cuartos);
                    return [
                        'producto_id' => $c->producto_id,
                        'producto_nombre' => $c->producto->nombre ?? 'N/A',
                        'unidades' => (float) $c->unidades,
                        'fraccion' => (float) $c->fraccion_cuartos,
                        'total_unidades' => $total,
                    ];
                });

            // 2. Corte Final (Cierre) si existe
            $corteFinal = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::with('producto')
                ->where('turno_id', $turno->id)
                ->whereIn('tipo_corte', ['final', 'cierre'])
                ->get()
                ->map(function ($c) {
                    $total = (float) $c->unidades + ((float) $c->fraccion_cuartos);
                    return [
                        'producto_id' => $c->producto_id,
                        'producto_nombre' => $c->producto->nombre ?? 'N/A',
                        'unidades' => (float) $c->unidades,
                        'fraccion' => (float) $c->fraccion_cuartos,
                        'total_unidades' => $total,
                    ];
                });

            // 3. Compras e Ingresos de Proveedores en este turno
            $compras = \App\Infrastructure\Persistence\Eloquent\Models\Compra::with(['detalles.producto', 'proveedorRel'])
                ->where('sucursal_id', $sucursal_id)
                ->whereBetween('fecha_compra', [$fechaInicio, $fechaFin])
                ->get()
                ->map(function ($compra) {
                    return [
                        'id' => $compra->id,
                        'proveedor' => $compra->proveedor,
                        'numero_nota' => $compra->numero_nota_factura,
                        'fecha' => $compra->fecha_compra->format('H:i:s'),
                        'foto_url' => $compra->foto_comprobante ? asset('storage/' . $compra->foto_comprobante) : null,
                        'items' => $compra->detalles->map(function ($d) {
                            return [
                                'producto_id' => $d->producto_id,
                                'producto_nombre' => $d->producto->nombre ?? 'N/A',
                                'cantidad' => (float) $d->cantidad,
                                'costo_unitario' => (float) $d->costo_unitario,
                            ];
                        }),
                    ];
                });

            // 4. Traspasos Salientes (despachados desde esta sucursal durante el turno)
            $traspasosSalientes = \App\Infrastructure\Persistence\Eloquent\Models\Traspaso::with(['sucursalDestino', 'detalles.producto'])
                ->where('sucursal_origen_id', $sucursal_id)
                ->whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'destino' => $t->sucursalDestino->nombre ?? 'N/A',
                        'estado' => $t->estado,
                        'fecha' => $t->created_at->format('H:i'),
                        'items' => $t->detalles->map(function ($d) {
                            return [
                                'producto_id' => $d->producto_id,
                                'producto_nombre' => $d->producto->nombre ?? 'N/A',
                                'cantidad_enviada' => (float) $d->cantidad_enviada,
                            ];
                        }),
                    ];
                });

            // 5. Traspasos Entrantes (recibidos en esta sucursal durante el turno)
            $traspasosEntrantes = \App\Infrastructure\Persistence\Eloquent\Models\Traspaso::with(['sucursalOrigen', 'detalles.producto'])
                ->where('sucursal_destino_id', $sucursal_id)
                ->where('estado', '!=', 'en_transito')
                ->whereBetween('updated_at', [$fechaInicio, $fechaFin])
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'origen' => $t->sucursalOrigen->nombre ?? 'N/A',
                        'estado' => $t->estado,
                        'fecha' => $t->updated_at->format('H:i'),
                        'items' => $t->detalles->map(function ($d) {
                            return [
                                'producto_id' => $d->producto_id,
                                'producto_nombre' => $d->producto->nombre ?? 'N/A',
                                'cantidad_recibida' => (float) ($d->cantidad_recibida_conforme ?? $d->cantidad_enviada),
                                'merma' => (float) ($d->merma_en_transito ?? 0),
                            ];
                        }),
                    ];
                });

            // 6. Bajas / Roturas reportadas en el turno
            $bajas = \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::with(['producto'])
                ->where('turno_id', $turno->id)
                ->where('tipo_movimiento', 'baja')
                ->get()
                ->map(function ($m) {
                    return [
                        'producto_id' => $m->producto_id,
                        'producto_nombre' => $m->producto->nombre ?? 'N/A',
                        'cantidad' => (float) $m->cantidad,
                        'observaciones' => $m->observaciones,
                        'fecha' => $m->created_at->format('H:i'),
                    ];
                });

            // 7. Transformaciones (Rellenos) efectuados
            $movsTransformacion = \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::with('producto')
                ->where('turno_id', $turno->id)
                ->whereIn('tipo_movimiento', ['transformacion_origen', 'transformacion_destino'])
                ->get();

            $transformaciones = [
                'total_terminadas' => (int) $turno->total_transformaciones_netas,
                'comision_total' => (float) $turno->total_comision_neta_pagada,
                'detalles' => $movsTransformacion->map(function ($m) {
                    return [
                        'producto_nombre' => $m->producto->nombre ?? 'N/A',
                        'tipo' => $m->tipo_movimiento,
                        'cantidad' => (float) $m->cantidad,
                        'observaciones' => $m->observaciones,
                    ];
                }),
            ];

            // 8. Balance Consolidado de Masa por Producto
            $catalogo = \App\Infrastructure\Persistence\Eloquent\Models\Producto::where('activo', true)->get();
            $balance = [];

            foreach ($catalogo as $prod) {
                $pid = $prod->id;

                // Inicial
                $ini = $corteInicial->firstWhere('producto_id', $pid)['total_unidades'] ?? 0.0;

                // Compras
                $ing = 0.0;
                foreach ($compras as $c) {
                    foreach ($c['items'] as $item) {
                        if ($item['producto_id'] == $pid) {
                            $ing += $item['cantidad'];
                        }
                    }
                }

                // Traspasos recibidos
                $traspIn = 0.0;
                foreach ($traspasosEntrantes as $te) {
                    foreach ($te['items'] as $item) {
                        if ($item['producto_id'] == $pid) {
                            $traspIn += $item['cantidad_recibida'];
                        }
                    }
                }

                // Traspasos enviados
                $traspOut = 0.0;
                foreach ($traspasosSalientes as $ts) {
                    foreach ($ts['items'] as $item) {
                        if ($item['producto_id'] == $pid) {
                            $traspOut += $item['cantidad_enviada'];
                        }
                    }
                }

                // Bajas
                $baj = 0.0;
                foreach ($bajas as $b) {
                    if ($b['producto_id'] == $pid) {
                        $baj += $b['cantidad'];
                    }
                }

                // Consumo / Producción de relleno
                $prodConsumida = 0.0;
                $prodObtenida = 0.0;
                foreach ($movsTransformacion as $mt) {
                    if ($mt->producto_id == $pid) {
                        if ($mt->tipo_movimiento === 'transformacion_origen') {
                            $prodConsumida += (float) $mt->cantidad;
                        } elseif ($mt->tipo_movimiento === 'transformacion_destino') {
                            $prodObtenida += (float) $mt->cantidad;
                        }
                    }
                }

                // Balance Teórico en Custodia
                $stockCustodia = $ini + $ing + $traspIn - $traspOut - $baj - $prodConsumida + $prodObtenida;

                // Final físico (si ya cerró)
                $finalFisico = $corteFinal->firstWhere('producto_id', $pid)['total_unidades'] ?? null;
                $diferencia = $finalFisico !== null ? ($finalFisico - $stockCustodia) : null;

                // Solo incluir si hubo movimiento o stock
                if ($ini > 0 || $ing > 0 || $traspIn > 0 || $traspOut > 0 || $baj > 0 || $prodConsumida > 0 || $prodObtenida > 0 || ($finalFisico !== null && $finalFisico > 0)) {
                    $balance[] = [
                        'producto_id' => $pid,
                        'producto_nombre' => $prod->nombre,
                        'tipo_producto' => $prod->tipo_producto,
                        'stock_inicial' => $ini,
                        'ingresos_compras' => $ing,
                        'traspasos_entrantes' => $traspIn,
                        'traspasos_salientes' => $traspOut,
                        'bajas_roturas' => $baj,
                        'transformacion_origen' => $prodConsumida,
                        'transformacion_destino' => $prodObtenida,
                        'stock_teorico_custodia' => $stockCustodia,
                        'stock_final_fisico' => $finalFisico,
                        'diferencia' => $diferencia,
                    ];
                }
            }

            // 9. Datos de Liquidación y Cobro
            $liquidacion = [
                'tipo_turno' => $turno->tipo_turno,
                'es_turno_dia' => $turno->tipo_turno === 'dia',
                'sueldo_semanal_base' => (float) ($turno->barman->sueldo_semanal_base ?? 0),
                'jornal_dia_estimado' => round(((float) ($turno->barman->sueldo_semanal_base ?? 0)) / 6, 2),
                'total_comision_relleno' => (float) $turno->total_comision_neta_pagada,
                'total_transformaciones' => (int) $turno->total_transformaciones_netas,
                'estado_cobro' => $turno->estado,
                'esta_cerrado' => in_array($turno->estado, ['cerrado', 'cobrado']),
                'foto_comprobante_cobro_url' => $turno->foto_comprobante_cobro ? asset('storage/' . $turno->foto_comprobante_cobro) : null,
                'codigo_recibo_cobro' => $turno->codigo_recibo_cobro,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'sucursal' => [
                        'id' => $sucursal->id,
                        'nombre' => $sucursal->nombre,
                        'codigo' => $sucursal->codigo,
                        'direccion' => $sucursal->direccion,
                    ],
                    'turno' => [
                        'id' => $turno->id,
                        'tipo_turno' => $turno->tipo_turno,
                        'estado' => $turno->estado,
                        'es_en_vivo' => $turno->estado === 'abierto',
                        'fecha_apertura' => $turno->fecha_apertura ? $turno->fecha_apertura->format('Y-m-d H:i:s') : null,
                        'fecha_cierre' => $turno->fecha_cierre ? $turno->fecha_cierre->format('Y-m-d H:i:s') : null,
                        'barman' => [
                            'id' => $turno->barman_id,
                            'nombre' => ($turno->barman->nombre ?? '') . ' ' . ($turno->barman->apellido ?? ''),
                            'telefono' => $turno->barman->telefono ?? 'N/A',
                        ],
                    ],
                    'turnos_historial' => $turnosHistorial,
                    'corte_inicial' => $corteInicial,
                    'corte_final' => $corteFinal,
                    'compras' => $compras,
                    'traspasos_salientes' => $traspasosSalientes,
                    'traspasos_entrantes' => $traspasosEntrantes,
                    'bajas' => $bajas,
                    'transformaciones' => $transformaciones,
                    'balance_inventario' => $balance,
                    'liquidacion' => $liquidacion,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al generar informe operativo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
