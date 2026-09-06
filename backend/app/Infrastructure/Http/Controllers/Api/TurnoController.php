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

            return response()->json([
                'success' => true,
                'message' => 'Turno abierto exitosamente',
                'data' => $resultado,
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
