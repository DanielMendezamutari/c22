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
                    $productoId = (int) $item['producto_id'];
                    $cantidadDeclarada = (float) $item['cantidad'];
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
            $resultado = $this->cerrarUseCase->ejecutar($id, $corteFinal);

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
            $turno = \App\Infrastructure\Persistence\Eloquent\Models\Turno::with(['sucursal', 'usuario'])->find($id);
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => "El turno #{$id} no existe.",
                ], 404);
            }

            // Ingresos acumulados durante el turno por producto (NUEVO INGRESO / Recepción de mercadería)
            $ingresosPorProd = \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::where('turno_id', $id)
                ->where('tipo_movimiento', 'ingreso_compra')
                ->groupBy('producto_id')
                ->selectRaw('producto_id, sum(cantidad) as total_ingresos')
                ->pluck('total_ingresos', 'producto_id')
                ->toArray();

            $cortes = \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::with('producto')
                ->where('turno_id', $id)
                ->whereIn('tipo_corte', ['inicial', 'apertura'])
                ->get()
                ->map(function ($c) use ($ingresosPorProd) {
                    $ingreso = (float) ($ingresosPorProd[$c->producto_id] ?? 0.0);
                    $inicial = (float) $c->cantidad;
                    return [
                        'producto_id' => $c->producto_id,
                        'producto_nombre' => $c->producto->nombre ?? 'Producto #' . $c->producto_id,
                        'codigo' => $c->producto->codigo_barra ?? 'S/C',
                        'tipo_producto' => $c->producto->tipo ?? 'terminado',
                        'cantidad' => $inicial,
                        'cantidad_inicial' => $inicial,
                        'ingresos' => $ingreso,
                        'total_disponible' => round($inicial + $ingreso, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'turno_id' => $turno->id,
                    'sucursal' => $turno->sucursal->nombre ?? 'Sucursal Punto Frío',
                    'barman' => ($turno->usuario->nombre ?? 'Barman') . ' ' . ($turno->usuario->apellido ?? ''),
                    'tipo_turno' => $turno->tipo_turno ?? 'noche',
                    'estado' => $turno->estado,
                    'fecha_apertura' => $turno->fecha_apertura ? $turno->fecha_apertura->toIso8601String() : now()->toIso8601String(),
                    'items' => $cortes,
                ],
            ]);
        } catch (Throwable $e) {
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
