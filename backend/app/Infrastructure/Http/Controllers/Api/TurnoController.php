<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Turnos\AbrirTurnoUseCase;
use App\Application\UseCases\Turnos\CerrarTurnoUseCase;
use App\Application\UseCases\Turnos\SolicitarCobroCajeraUseCase;
use App\Infrastructure\Http\Requests\CorteInventarioRequest;
use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\CorteInventario;
use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaReconteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

class TurnoController extends Controller

{
    private SolicitarCobroCajeraUseCase $cobroUseCase;
    private AbrirTurnoUseCase $abrirUseCase;
    private CerrarTurnoUseCase $cerrarUseCase;

    public function __construct(
        SolicitarCobroCajeraUseCase $cobroUseCase,
        AbrirTurnoUseCase $abrirUseCase,
        CerrarTurnoUseCase $cerrarUseCase
    ) {
        $this->cobroUseCase = $cobroUseCase;
        $this->abrirUseCase = $abrirUseCase;
        $this->cerrarUseCase = $cerrarUseCase;
    }

    public function abrirTurno(CorteInventarioRequest $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            $barmanId = (int) ($request->input('barman_id') ?? $user?->id ?? 1);

            $datos = $request->validated();
            $datos['barman_id'] = $barmanId;

            // Soporte de suplencia por Cajera o Admin
            if ($user && $user->rol === 'cajera') {
                $datos['realizado_por_usuario_id'] = $user->id;
                $datos['es_suplencia'] = true;
            } elseif ($request->boolean('es_suplencia') && $user) {
                $datos['realizado_por_usuario_id'] = $user->id;
                $datos['es_suplencia'] = true;
            }

            $resultado = $this->abrirUseCase->ejecutar($datos);
            $nuevoTurnoId = is_array($resultado) ? ($resultado['id'] ?? null) : $resultado->id;

            // Detección automática de discrepancias entre turnos consecutivos (Fuga inter-turnos)
            $sucursalId = (int) ($datos['sucursal_id'] ?? 1);
            $discrepancias = [];

            $turnoSaliente = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with('usuario')
                ->where('sucursal_id', $sucursalId)
                ->where('id', '!=', $nuevoTurnoId)
                ->whereIn('estado', ['cerrado', 'cobrado', 'auditado'])
                ->latest('id')
                ->first();

            if ($turnoSaliente) {
                $cortesCierreAnterior = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $turnoSaliente->id)
                    ->whereIn('tipo_corte', ['final', 'cierre'])
                    ->pluck('cantidad', 'producto_id')
                    ->toArray();

                $corteInicial = $datos['corte_inicial'] ?? [];
                foreach ($corteInicial as $item) {
                    $esProvisional = (bool) ($item['es_provisional'] ?? false);
                    $cantidadDeclarada = (float) $item['cantidad'];

                    if ($esProvisional) {
                        $nombreProv = $item['nombre_provisional'] ?? 'Producto No Catalogado';
                        \App\Infrastructure\Persistence\Eloquent\Models\AlertaDiscrepancia::create([
                            'sucursal_id' => $sucursalId,
                            'turno_saliente_id' => $turnoSaliente ? $turnoSaliente->id : $nuevoTurnoId,
                            'turno_entrante_id' => $nuevoTurnoId,
                            'producto_id' => null,
                            'stock_esperado' => 0.0,
                            'stock_declarado' => $cantidadDeclarada,
                            'diferencia' => $cantidadDeclarada,
                            'resuelto' => false,
                            'observaciones' => "[PRODUCTO PROVISIONAL]: El barman contabilizó '{$nombreProv}' ({$cantidadDeclarada} unid.) no listado en el catálogo.",
                            'fecha_alerta' => now(),
                        ]);

                        $discrepancias[] = [
                            'producto_id' => null,
                            'producto_nombre' => "[PROVISIONAL] " . $nombreProv,
                            'stock_esperado' => 0.0,
                            'stock_declarado' => $cantidadDeclarada,
                            'diferencia' => $cantidadDeclarada,
                            'turno_saliente_barman' => 'Barra (No Listado)',
                            'es_provisional' => true,
                            'nombre_provisional' => $nombreProv,
                        ];
                    } else {
                        $productoId = (int) ($item['producto_id'] ?? 0);
                        $stockEsperado = (float) ($cortesCierreAnterior[$productoId] ?? 0.0);
                        $diferencia = round($cantidadDeclarada - $stockEsperado, 2);

                        if (abs($diferencia) >= 0.01) {
                            \App\Infrastructure\Persistence\Eloquent\Models\AlertaDiscrepancia::create([
                                'sucursal_id' => $sucursalId,
                                'turno_saliente_id' => $turnoSaliente->id,
                                'turno_entrante_id' => $nuevoTurnoId,
                                'producto_id' => $productoId,
                                'stock_esperado' => $stockEsperado,
                                'stock_declarado' => $cantidadDeclarada,
                                'diferencia' => $diferencia,
                                'resuelto' => false,
                                'observaciones' => "Discrepancia en apertura: Cierre previo {$stockEsperado}, Apertura entrante {$cantidadDeclarada}",
                                'fecha_alerta' => now(),
                            ]);

                            $prod = \App\Infrastructure\Persistence\Eloquent\Models\Producto::find($productoId);
                            $discrepancias[] = [
                                'producto_id' => $productoId,
                                'producto_nombre' => $prod ? $prod->nombre : "Producto #$productoId",
                                'stock_esperado' => $stockEsperado,
                                'stock_declarado' => $cantidadDeclarada,
                                'diferencia' => $diferencia,
                                'turno_saliente_barman' => $turnoSaliente->usuario ? ($turnoSaliente->usuario->nombre . ' ' . $turnoSaliente->usuario->apellido) : 'Turno Anterior',
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Turno abierto exitosamente',
                'data' => $resultado,
                'tiene_discrepancias' => count($discrepancias) > 0,
                'discrepancias' => $discrepancias,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function cerrarTurno(int $id, CorteInventarioRequest $request): JsonResponse
    {
        try {
            $corteFinal = $request->input('corte_final', []);
            $user = auth('sanctum')->user() ?? $request->user();

            $opciones = [];
            if ($user && $user->rol === 'cajera') {
                $opciones['cerrado_por_usuario_id'] = $user->id;
                $opciones['es_suplencia'] = true;
            } elseif ($request->boolean('es_suplencia') && $user) {
                $opciones['cerrado_por_usuario_id'] = $user->id;
                $opciones['es_suplencia'] = true;
            }

            $resultado = $this->cerrarUseCase->ejecutar($id, $corteFinal, $opciones);

            return response()->json([
                'success' => true,
                'message' => 'Turno cerrado exitosamente',
                'data' => $resultado,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function resumenCajera(int $id): JsonResponse
    {
        try {
            $resumen = $this->cobroUseCase->obtenerResumen($id);
            return response()->json([
                'success' => true,
                'data' => $resumen,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function cobroRecibido(Request $request, int $id): JsonResponse
    {
        try {
            $foto = $request->input('foto_comprobante') ?? $request->file('foto_comprobante') ?? $request->file('foto');
            $resultado = $this->cobroUseCase->confirmarCobro($id, $foto);
            return response()->json([
                'success' => $resultado['exito'] ?? true,
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function productosParaCorte(): JsonResponse
    {
        $productos = Producto::where('activo', true)
            ->select('id', 'nombre', 'codigo_barra', 'tipo', 'tipo as tipo_producto', 'unidad_medida', 'es_transformable')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $productos,
        ]);
    }

    public function corteInicial(int $id): JsonResponse
    {
        try {
            $turno = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario', 'realizadoPor', 'cerradoPor', 'reconteoAutorizadoPor'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => "El turno #{$id} no existe.",
                ], 404);
            }

            // Cortes iniciales
            $cortesIniciales = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::with('producto')
                ->where('turno_id', $id)
                ->whereIn('tipo_corte', ['inicial', 'apertura'])
                ->get()
                ->keyBy('producto_id');

            // Movimientos del turno
            $movs = \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::with('producto')
                ->where('turno_id', $id)
                ->get();

            $movsPorProd = $movs->groupBy('producto_id');

            // Todos los IDs involucrados en el turno
            $todosProductoIds = $cortesIniciales->keys()->merge($movsPorProd->keys())->unique();

            // Cargar productos del catálogo ordenados alfabéticamente
            $productos = \App\Infrastructure\Persistence\Eloquent\Models\Producto::whereIn('id', $todosProductoIds)
                ->orderBy('nombre')
                ->get()
                ->keyBy('id');

            $items = $productos->map(function ($prod) use ($cortesIniciales, $movsPorProd) {
                $pid = $prod->id;
                $corte = $cortesIniciales->get($pid);
                $pMovs = $movsPorProd->get($pid) ?? collect();

                $inicial = $corte ? (float) $corte->cantidad : 0.0;

                $ingresos = (float) $pMovs->whereIn('tipo_movimiento', ['ingreso', 'traspaso_entrada'])->sum('cantidad');
                $rellenosProd = (float) $pMovs->whereIn('tipo_movimiento', ['transformacion_produccion', 'transformacion_destino'])->sum('cantidad');
                $rellenosCons = (float) $pMovs->whereIn('tipo_movimiento', ['transformacion_consumo', 'transformacion_origen'])->sum('cantidad');
                $rellenosNetos = round($rellenosProd - $rellenosCons, 2);
                $bajas = (float) $pMovs->whereIn('tipo_movimiento', ['baja_rotura', 'baja'])->sum('cantidad');
                $traspasosSalida = (float) $pMovs->where('tipo_movimiento', 'traspaso_salida')->sum('cantidad');

                $disp = round($inicial + $ingresos + $rellenosProd - $rellenosCons - $bajas - $traspasosSalida, 2);

                $nombre = $prod->nombre ?? ($corte->producto->nombre ?? 'Producto #' . $pid);

                return [
                    'producto_id' => (int) $pid,
                    'nombre' => $nombre,
                    'producto_nombre' => $nombre,
                    'codigo' => $prod->codigo_barra ?? 'S/C',
                    'tipo_producto' => $prod->tipo ?? 'terminado',
                    'cantidad' => $inicial,
                    'cantidad_inicial' => $inicial,
                    'ingresos' => $ingresos,
                    'rellenos_producidos' => $rellenosProd,
                    'rellenos_consumidos' => $rellenosCons,
                    'rellenos' => $rellenosNetos,
                    'bajas' => $bajas,
                    'traspasos_salida' => $traspasosSalida,
                    'total_disponible' => max(0.0, $disp),
                ];
            })->values();

            // Incorporar ítems provisionales contados en el turno
            $cortesProvisionales = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $id)
                ->whereIn('tipo_corte', ['inicial', 'apertura'])
                ->where('es_provisional', true)
                ->get();

            $itemsProvisionales = $cortesProvisionales->map(function ($corte) {
                $nombre = $corte->nombre_provisional ?? 'Producto Provisional';
                $cant = (float) $corte->cantidad;
                return [
                    'producto_id' => null,
                    'nombre' => $nombre,
                    'producto_nombre' => $nombre,
                    'codigo' => 'PROV',
                    'tipo_producto' => $corte->es_licor ? 'licor' : 'terminado',
                    'cantidad' => $cant,
                    'cantidad_inicial' => $cant,
                    'ingresos' => 0.0,
                    'rellenos_producidos' => 0.0,
                    'rellenos_consumidos' => 0.0,
                    'rellenos' => 0.0,
                    'bajas' => 0.0,
                    'traspasos_salida' => 0.0,
                    'total_disponible' => $cant,
                    'es_provisional' => true,
                    'nombre_provisional' => $nombre,
                ];
            });

            $todosLosItems = $items->concat($itemsProvisionales)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'turno_id' => $turno->id,
                    'sucursal' => $turno->sucursal->nombre ?? 'Sucursal Punto Frío',
                    'barman' => ($turno->usuario->nombre ?? 'Barman') . ' ' . ($turno->usuario->apellido ?? ''),
                    'tipo_turno' => $turno->tipo_turno ?? 'noche',
                    'estado' => $turno->estado,
                    'es_suplencia' => (bool) ($turno->es_suplencia ?? false),
                    'permite_reconteo' => (bool) ($turno->permite_reconteo ?? false),
                    'reconteo_tipo' => $turno->reconteo_tipo,
                    'reconteo_motivo' => $turno->reconteo_motivo,
                    'reconteo_autorizado_por' => $turno->reconteoAutorizadoPor ? ($turno->reconteoAutorizadoPor->nombre . ' ' . $turno->reconteoAutorizadoPor->apellido) : null,
                    'reconteo_autorizado_at' => $turno->reconteo_autorizado_at ? $turno->reconteo_autorizado_at->toIso8601String() : null,
                    'realizado_por' => $turno->realizadoPor ? ($turno->realizadoPor->nombre . ' ' . $turno->realizadoPor->apellido) : null,
                    'cerrado_por' => $turno->cerradoPor ? ($turno->cerradoPor->nombre . ' ' . $turno->cerradoPor->apellido) : null,
                    'fecha_apertura' => $turno->fecha_apertura ? $turno->fecha_apertura->toIso8601String() : now()->toIso8601String(),
                    'items' => $todosLosItems,
                ],
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function confirmarPagoComision(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'foto' => 'required|image|max:5120',
                'observacion' => 'nullable|string|max:500',
            ]);

            $turno = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['barman', 'sucursal'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno no encontrado.',
                ], 404);
            }

            $fotoPath = $request->file('foto')->store('comprobantes_comisiones', 'public');
            $user = auth('sanctum')->user() ?? $request->user();
            $nombreCobrador = $user ? ($user->nombre . ' ' . $user->apellido) : 'Cajera';

            $codigoRecibo = 'REC-' . strtoupper(substr(md5(uniqid((string)$id, true)), 0, 6));

            $turno->update([
                'estado' => 'cobrado',
                'foto_comprobante_cobro' => $fotoPath,
                'fecha_cobro' => now(),
                'observaciones' => trim(($turno->observaciones ?? '') . "\n[Cobro {$codigoRecibo} por {$nombreCobrador}]: " . ($request->input('observacion') ?? '')),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Desembolso de comisiones confirmado exitosamente',
                'data' => [
                    'turno_id' => $turno->id,
                    'estado' => $turno->estado,
                    'total_comision_neta_pagada' => (float) ($turno->total_comision_bruta ?? 0.0),
                    'cobrado_por' => $nombreCobrador,
                    'fecha_cobro' => $turno->fecha_cobro ? $turno->fecha_cobro->toDateTimeString() : now()->toDateTimeString(),
                    'codigo_recibo' => $codigoRecibo,
                    'foto_url' => asset('storage/' . $fotoPath),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function historialCortes(Request $request): JsonResponse
    {
        try {
            $query = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario'])
                ->whereIn('estado', ['cerrado', 'cobrado', 'auditado'])
                ->orderBy('id', 'desc');

            if ($request->filled('sucursal_id') && (int) $request->sucursal_id > 0) {
                $query->where('sucursal_id', (int) $request->sucursal_id);
            }

            $turnos = $query->limit(30)->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'sucursal_id' => $t->sucursal_id,
                    'sucursal_nombre' => $t->sucursal->nombre ?? 'N/A',
                    'barman_nombre' => ($t->usuario->nombre ?? 'Barman') . ' ' . ($t->usuario->apellido ?? ''),
                    'tipo_turno' => $t->tipo_turno,
                    'estado' => $t->estado,
                    'fecha_apertura' => $t->fecha_apertura ? $t->fecha_apertura->toDateTimeString() : null,
                    'fecha_cierre' => $t->fecha_cierre ? $t->fecha_cierre->toDateTimeString() : null,
                    'total_comision_bruta' => (float) $t->total_comision_bruta,
                    'permite_reconteo' => (bool) ($t->permite_reconteo ?? false),
                    'reconteo_tipo' => $t->reconteo_tipo,
                    'reconteo_motivo' => $t->reconteo_motivo,
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

    public function turnoActivo(Request $request): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            $barmanId = (int) ($request->input('barman_id') ?? $user?->id ?? 1);
            $sucursalId = (int) ($request->input('sucursal_id') ?? 1);

            $turno = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario'])
                ->where('barman_id', $barmanId)
                ->where('sucursal_id', $sucursalId)
                ->whereIn('estado', ['abierto', 'cobrado'])
                ->latest('fecha_apertura')
                ->first();

            // Si el barman no tiene turno directo pero existe un turno abierto en la sucursal (ej. asignado a default/admin), auto-adoptarlo
            if (!$turno) {
                $turnoHuerfano = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario'])
                    ->where('sucursal_id', $sucursalId)
                    ->whereIn('estado', ['abierto', 'cobrado'])
                    ->where('barman_id', 1)
                    ->latest('fecha_apertura')
                    ->first();

                if ($turnoHuerfano) {
                    $turnoHuerfano->update(['barman_id' => $barmanId]);
                    $turno = $turnoHuerfano->fresh(['sucursal', 'usuario']);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $turno ? [
                    'id' => $turno->id,
                    'sucursal_id' => $turno->sucursal_id,
                    'sucursal_nombre' => $turno->sucursal->nombre ?? 'N/A',
                    'barman_id' => $turno->barman_id,
                    'barman_nombre' => ($turno->usuario->nombre ?? 'Barman') . ' ' . ($turno->usuario->apellido ?? ''),
                    'tipo_turno' => $turno->tipo_turno,
                    'estado' => $turno->estado,
                    'fecha_apertura' => $turno->fecha_apertura ? $turno->fecha_apertura->toIso8601String() : null,
                    'total_transformaciones_netas' => (int) $turno->total_transformaciones_netas,
                    'total_comision_bruta' => (float) $turno->total_comision_bruta,
                    'permite_reconteo' => (bool) ($turno->permite_reconteo ?? false),
                    'reconteo_tipo' => $turno->reconteo_tipo,
                    'reconteo_motivo' => $turno->reconteo_motivo,
                ] : null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function autorizarReconteo(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            if ($user && $user->rol !== 'admin') {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo un usuario con rol de Administrador puede autorizar reconteos.',
                ], 403);
            }

            $request->validate([
                'tipo_corte' => 'required|in:apertura,cierre',
                'motivo' => 'required|string|max:255',
            ]);

            $turno = Turno::with(['sucursal', 'usuario'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => "El turno #{$id} no existe.",
                ], 404);
            }

            $tipoCorte = $request->input('tipo_corte');
            $motivo = $request->input('motivo');

            $turno->update([
                'permite_reconteo' => true,
                'reconteo_tipo' => $tipoCorte,
                'reconteo_autorizado_por_id' => $user?->id ?? 1,
                'reconteo_autorizado_at' => now(),
                'reconteo_motivo' => $motivo,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Reconteo autorizado exitosamente para corte de {$tipoCorte}",
                'data' => [
                    'turno_id' => $turno->id,
                    'permite_reconteo' => true,
                    'reconteo_tipo' => $tipoCorte,
                    'reconteo_autorizado_por' => ($user->nombre ?? 'Admin') . ' ' . ($user->apellido ?? ''),
                    'reconteo_autorizado_at' => now()->toIso8601String(),
                    'reconteo_motivo' => $motivo,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function aplicarReconteo(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            $turno = Turno::with(['sucursal', 'usuario', 'reconteoAutorizadoPor'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => "El turno #{$id} no existe.",
                ], 404);
            }

            if (!$turno->permite_reconteo) {
                return response()->json([
                    'success' => false,
                    'error' => 'El turno no tiene autorización activa para reconteo o el permiso ya expiró.',
                ], 400);
            }

            $request->validate([
                'tipo_corte' => 'required|in:apertura,cierre',
                'corte_corregido' => 'required|array|min:1',
            ]);

            $tipoCorte = $request->input('tipo_corte');
            $corteCorregido = $request->input('corte_corregido');

            $cambiosRealizados = [];

            DB::transaction(function () use ($turno, $tipoCorte, $corteCorregido, $user, &$cambiosRealizados) {
                $dbTipos = ($tipoCorte === 'apertura') ? ['inicial', 'apertura'] : ['final', 'cierre'];

                // Cortes previos del turno para este tipo
                $cortesPrevios = CorteInventario::with('producto')
                    ->where('turno_id', $turno->id)
                    ->whereIn('tipo_corte', $dbTipos)
                    ->get();

                $cortesPorProducto = $cortesPrevios->keyBy('producto_id');

                foreach ($corteCorregido as $item) {
                    $pid = isset($item['producto_id']) && $item['producto_id'] !== null ? (int) $item['producto_id'] : null;
                    $cantNueva = (float) ($item['cantidad'] ?? 0.0);
                    $esProv = (bool) ($item['es_provisional'] ?? false);
                    $nombreProv = $item['nombre_provisional'] ?? ($item['nombre'] ?? null);

                    $corteExistente = null;
                    if ($pid) {
                        $corteExistente = $cortesPorProducto->get($pid);
                    } elseif ($esProv && $nombreProv) {
                        $corteExistente = $cortesPrevios->where('es_provisional', true)->where('nombre_provisional', $nombreProv)->first();
                    }

                    $cantAnterior = $corteExistente ? (float) $corteExistente->cantidad : 0.0;
                    $nombreProd = $corteExistente?->producto?->nombre ?? ($nombreProv ?? "Producto #{$pid}");

                    if (abs($cantNueva - $cantAnterior) > 0.001) {
                        $diff = round($cantNueva - $cantAnterior, 2);
                        $cambiosRealizados[] = [
                            'producto_id' => $pid,
                            'nombre_producto' => $nombreProd,
                            'valor_anterior' => $cantAnterior,
                            'valor_nuevo' => $cantNueva,
                            'diferencia' => $diff,
                        ];

                        if ($corteExistente) {
                            $corteExistente->update(['cantidad' => $cantNueva]);
                        } else {
                            CorteInventario::create([
                                'turno_id' => $turno->id,
                                'producto_id' => $pid,
                                'es_provisional' => $esProv,
                                'nombre_provisional' => $nombreProv,
                                'tipo_corte' => ($tipoCorte === 'apertura') ? 'inicial' : 'final',
                                'cantidad' => $cantNueva,
                            ]);
                        }
                    }
                }

                // Registrar log de auditoría
                $adminId = $turno->reconteo_autorizado_por_id ?? ($user?->rol === 'admin' ? $user->id : 1);
                $usuarioId = $user?->id ?? $turno->barman_id;

                AuditoriaReconteo::create([
                    'turno_id' => $turno->id,
                    'usuario_id' => $usuarioId,
                    'admin_id' => $adminId,
                    'tipo_corte' => $tipoCorte,
                    'motivo' => $turno->reconteo_motivo ?? 'Corrección de error de conteo',
                    'detalles_json' => $cambiosRealizados,
                ]);

                // Auto-consumir permiso de reconteo (Inmutabilidad re-sellada)
                $turno->update([
                    'permite_reconteo' => false,
                    'reconteo_tipo' => null,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Reconteo aplicado exitosamente. El turno ha sido re-sellado y el inventario recalculado.',
                'data' => [
                    'turno_id' => $turno->id,
                    'tipo_corte' => $tipoCorte,
                    'permite_reconteo' => false,
                    'cambios_realizados' => $cambiosRealizados,
                    'fecha_reconteo' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Herramienta técnica exclusiva para Daniel (Super Admin):
     * Fuerza el recálculo y la reconciliación total de saldos del turno en curso.
     */
    public function forzarResincronizacion(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth('sanctum')->user() ?? $request->user();
            if ($user && !in_array($user->rol, ['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acceso denegado: Solo el Super Administrador puede forzar resincronización de turnos.',
                ], 403);
            }

            $turno = Turno::with(['sucursal', 'barman', 'movimientos'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => "El turno #{$id} no existe.",
                ], 404);
            }

            // Recalcular métricas en base a movimientos reales
            $totalTransformaciones = MovimientoInventario::where('turno_id', $turno->id)
                ->where('tipo_movimiento', 'transformacion')
                ->sum('cantidad');

            $totalBajas = MovimientoInventario::where('turno_id', $turno->id)
                ->where('tipo_movimiento', 'baja')
                ->sum('cantidad');

            $totalIngresos = MovimientoInventario::where('turno_id', $turno->id)
                ->whereIn('tipo_movimiento', ['ingreso', 'traspaso_entrada'])
                ->sum('cantidad');

            // Actualizar turno
            $turno->total_transformaciones_netas = (int) $totalTransformaciones;
            $turno->total_comision_bruta = (float) $totalTransformaciones * 1.0;
            $turno->save();

            \Illuminate\Support\Facades\Log::info("[SuperAdmin] Resincronización técnica forzada para Turno #{$id}", [
                'sucursal' => $turno->sucursal->nombre ?? 'N/A',
                'ejecutado_por' => $user ? "{$user->nombre} ({$user->rol})" : 'SuperAdmin Console',
                'transformaciones' => $totalTransformaciones,
                'bajas' => $totalBajas,
                'ingresos' => $totalIngresos,
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Turno #{$id} ({$turno->sucursal->nombre}) resincronizado exitosamente.",
                'data' => [
                    'turno_id' => $turno->id,
                    'sucursal' => $turno->sucursal->nombre ?? 'N/A',
                    'total_transformaciones_netas' => $turno->total_transformaciones_netas,
                    'total_comision_bruta' => (float) $turno->total_comision_bruta,
                    'total_bajas' => (float) $totalBajas,
                    'total_ingresos' => (float) $totalIngresos,
                    'resincronizado_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Liquida de inmediato el jornal de barra del garzón de turno día (US35)
     * POST /api/v1/turnos/{id}/liquidar-garzon
     */
    public function liquidarGarzon(int $id, Request $request, \App\Application\UseCases\Turnos\LiquidarJornalGarzonUseCase $useCase): JsonResponse
    {
        try {
            $validated = $request->validate([
                'usuario_id' => 'required|exists:usuarios,id',
                'jornal_base_bs' => 'nullable|numeric|min:0',
                'faltante_botellas_unidades' => 'nullable|numeric|min:0',
                'descuento_faltante_bs' => 'nullable|numeric|min:0',
                'costo_unitario_bs' => 'nullable|numeric|min:0',
                'foto_comprobante_url' => 'nullable|string',
            ]);

            $datos = array_merge($validated, [
                'turno_id' => $id,
                'fecha' => now()->toDateString(),
            ]);

            $jornal = $useCase->execute($datos);

            return response()->json([
                'success' => true,
                'message' => 'Jornal de garzón liquidado exitosamente con descuento por botellas faltantes aplicado.',
                'data' => $jornal,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al liquidar jornal de garzón: ' . $e->getMessage(),
            ], 400);
        }
    }
}

