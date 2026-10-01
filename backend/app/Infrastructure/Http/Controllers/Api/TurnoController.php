<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Turnos\AbrirTurnoUseCase;
use App\Application\UseCases\Turnos\CerrarTurnoUseCase;
use App\Application\UseCases\Turnos\SolicitarCobroCajeraUseCase;
use App\Infrastructure\Http\Requests\CorteInventarioRequest;
use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            $turno = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario', 'realizadoPor', 'cerradoPor'])->find($id);
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
                ] : null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
