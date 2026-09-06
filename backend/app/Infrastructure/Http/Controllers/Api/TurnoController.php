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
            $user = $request->user();
            $barmanId = $user ? $user->id : (int) $request->input('barman_id', 1);

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
}
