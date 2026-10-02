<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Auditoria\ValidarTarifaTaxiUseCase;
use App\Infrastructure\AI\TaxiParserService;
use App\Infrastructure\Persistence\Eloquent\Models\RegistroTrasladoTaxi;
use App\Infrastructure\Persistence\Eloquent\Models\TarifaRutaTaxi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class TaxiController extends Controller
{
    private TaxiParserService $parserService;
    private ValidarTarifaTaxiUseCase $validarUseCase;

    public function __construct(
        TaxiParserService $parserService,
        ValidarTarifaTaxiUseCase $validarUseCase
    ) {
        $this->parserService = $parserService;
        $this->validarUseCase = $validarUseCase;
    }

    /**
     * Procesa un mensaje de texto de WhatsApp sobre rotación de chicas o taxis con IA.
     */
    public function procesarMensaje(Request $request): JsonResponse
    {
        $request->validate([
            'mensaje' => 'required|string',
            'fecha_hora' => 'nullable|date',
        ]);

        try {
            $parsed = $this->parserService->parsearMensajeTaxi($request->input('mensaje'));

            if ($request->filled('fecha_hora')) {
                $parsed['fecha_hora'] = $request->input('fecha_hora');
            }

            $registro = $this->validarUseCase->ejecutar($parsed);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje de taxi procesado y auditado con IA correctamente.',
                'data' => $registro,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna el consolidado diario de gastos de taxis, rotación y alertas de sobreprecio.
     */
    public function reporteDiario(Request $request): JsonResponse
    {
        $fecha = $request->input('fecha', Carbon::now()->toDateString());

        $registros = RegistroTrasladoTaxi::with(['origen', 'destino'])
            ->whereDate('fecha_hora', $fecha)
            ->latest('fecha_hora')
            ->get();

        $totalGasto = $registros->sum('monto_cobrado_bs');
        $totalSobreprecio = $registros->sum('sobreprecio_detectado_bs');
        $carrerasDuplicadas = $registros->where('es_duplicado_horario', true)->count();
        $sobrepreciosContador = $registros->where('estado_auditoria', 'sobreprecio_detectado')->count();

        return response()->json([
            'success' => true,
            'fecha' => $fecha,
            'resumen' => [
                'total_carreras' => $registros->count(),
                'total_gasto_bs' => round($totalGasto, 2),
                'total_sobreprecio_bs' => round($totalSobreprecio, 2),
                'carreras_duplicadas' => $carrerasDuplicadas,
                'alertas_sobreprecio' => $sobrepreciosContador,
            ],
            'registros' => $registros,
        ]);
    }

    /**
     * Retorna las tarifas estándar configuradas entre sucursales.
     */
    public function listarTarifas(): JsonResponse
    {
        $tarifas = TarifaRutaTaxi::with(['origen', 'destino'])
            ->where('activo', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tarifas,
        ]);
    }
}
