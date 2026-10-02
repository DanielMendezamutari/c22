<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Application\UseCases\Auditoria\ConciliarRecaudacionUseCase;
use App\Infrastructure\AI\GeminiVisionService;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\RecaudacionDiaria;
use App\Infrastructure\Persistence\Eloquent\Models\VoucherDeposito;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecaudacionController extends Controller
{
    private GeminiVisionService $geminiService;
    private ConciliarRecaudacionUseCase $conciliarUseCase;

    public function __construct(
        GeminiVisionService $geminiService,
        ConciliarRecaudacionUseCase $conciliarUseCase
    ) {
        $this->geminiService = $geminiService;
        $this->conciliarUseCase = $conciliarUseCase;
    }

    /**
     * Procesa una fotografía de planilla física manuscrita de caja con IA.
     */
    public function procesarPlanilla(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'fecha_operativa' => 'required|date',
            'foto' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'foto_base64' => 'nullable|string',
            'foto_url' => 'nullable|string',
            'total_ventas_manual' => 'nullable|numeric',
            'total_gastos_manual' => 'nullable|numeric',
            'monto_sobre_manual' => 'nullable|numeric',
        ]);

        try {
            $fotoUrl = 'uploads/planillas/default.jpg';
            $imageForOcr = '';

            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $path = $file->store('planillas', 'public');
                $fotoUrl = Storage::url($path);
                $imageForOcr = $file->getRealPath();
            } elseif ($request->filled('foto_base64')) {
                $fotoBase64 = $request->input('foto_base64');
                $imageForOcr = $fotoBase64;
                $fotoUrl = 'data:image/jpeg;base64,' . substr($fotoBase64, 0, 30) . '...';
            } elseif ($request->filled('foto_url')) {
                $fotoUrl = $request->input('foto_url');
                $imageForOcr = $fotoUrl;
            }

            // Ejecutar extracción OCR con Gemini Vision si hay imagen
            $ocrData = !empty($imageForOcr) ? $this->geminiService->procesarPlanillaManuscrita($imageForOcr) : [];

            $totalVentas = $request->input('total_ventas_manual') ?? ($ocrData['total_ventas_declaradas_bs'] ?? 0.0);
            $totalGastos = $request->input('total_gastos_manual') ?? ($ocrData['total_gastos_declarados_bs'] ?? 0.0);
            $montoSobre = $request->input('monto_sobre_manual') ?? ($ocrData['monto_sobre_efectivo_bs'] ?? max(0, $totalVentas - $totalGastos));
            $cajeroNombre = $ocrData['cajero_nombre'] ?? $request->input('cajero_nombre', 'Cajero');

            $user = auth('sanctum')->user() ?? $request->user();

            $planilla = PlanillaCaja::create([
                'sucursal_id' => (int) $request->input('sucursal_id'),
                'turno_id' => $request->input('turno_id'),
                'fecha_operativa' => Carbon::parse($request->input('fecha_operativa'))->toDateString(),
                'foto_url' => $fotoUrl,
                'total_ventas_declaradas_bs' => (float) $totalVentas,
                'total_gastos_declarados_bs' => (float) $totalGastos,
                'monto_sobre_efectivo_bs' => (float) $montoSobre,
                'cajero_nombre' => $cajeroNombre,
                'datos_ocr_json' => $ocrData,
                'estado_ocr' => !empty($ocrData) ? 'procesado' : 'corregido_manual',
                'auditado_por_id' => $user?->id,
                'observaciones' => $request->input('observaciones'),
            ]);

            // Reconciliar automáticamente con la recaudación del día
            $conciliacion = $this->conciliarUseCase->ejecutar(
                $planilla->sucursal_id,
                $planilla->fecha_operativa,
                $planilla->id,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Planilla física procesada y analizada con Gemini Vision exitosamente.',
                'data' => [
                    'planilla' => $planilla,
                    'conciliacion' => $conciliacion,
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
     * Procesa una fotografía de voucher de depósito bancario entregado por el recaudador con IA.
     */
    public function procesarVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'fecha' => 'required|date',
            'foto' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'foto_base64' => 'nullable|string',
            'foto_url' => 'nullable|string',
            'monto_manual' => 'nullable|numeric',
            'nro_operacion' => 'nullable|string',
            'banco_nombre' => 'nullable|string',
        ]);

        try {
            $fotoUrl = 'uploads/vouchers/default.jpg';
            $imageForOcr = '';

            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $path = $file->store('vouchers', 'public');
                $fotoUrl = Storage::url($path);
                $imageForOcr = $file->getRealPath();
            } elseif ($request->filled('foto_base64')) {
                $fotoBase64 = $request->input('foto_base64');
                $imageForOcr = $fotoBase64;
                $fotoUrl = 'data:image/jpeg;base64,' . substr($fotoBase64, 0, 30) . '...';
            } elseif ($request->filled('foto_url')) {
                $fotoUrl = $request->input('foto_url');
                $imageForOcr = $fotoUrl;
            }

            // Extracción con Gemini Vision
            $ocrData = !empty($imageForOcr) ? $this->geminiService->procesarVoucherBancario($imageForOcr) : [];

            $montoDepositado = $request->input('monto_manual') ?? ($ocrData['monto_depositado_bs'] ?? 0.0);
            $nroOperacion = $request->input('nro_operacion') ?? ($ocrData['nro_operacion'] ?? 'OP-' . time());
            $bancoNombre = $request->input('banco_nombre') ?? ($ocrData['banco_nombre'] ?? 'BANCO');

            $user = auth('sanctum')->user() ?? $request->user();

            $voucher = VoucherDeposito::create([
                'banco_nombre' => $bancoNombre,
                'nro_operacion' => $nroOperacion,
                'fecha_deposito' => Carbon::now(),
                'monto_depositado_bs' => (float) $montoDepositado,
                'titular_cuenta' => $ocrData['titular_cuenta'] ?? 'Grupo Punto Frío',
                'foto_url' => $fotoUrl,
                'recaudador_usuario_id' => $user?->id,
                'datos_ocr_json' => $ocrData,
                'estado_ocr' => !empty($ocrData) ? 'procesado' : 'corregido_manual',
            ]);

            // Reconciliar con la planilla y comprobar faltantes / emitir alertas
            $conciliacion = $this->conciliarUseCase->ejecutar(
                (int) $request->input('sucursal_id'),
                $request->input('fecha'),
                null,
                $voucher->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Voucher bancario procesado con éxito y conciliado con el sobre de caja.',
                'data' => [
                    'voucher' => $voucher,
                    'conciliacion' => $conciliacion,
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
     * Retorna el listado consolidado de recaudaciones diarias y auditorías para la web.
     */
    public function listarRecaudacionesDiarias(Request $request): JsonResponse
    {
        $query = RecaudacionDiaria::with(['sucursal', 'planilla', 'voucher'])
            ->latest('fecha');

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->input('sucursal_id'));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->input('fecha_hasta'));
        }

        $recaudaciones = $query->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $recaudaciones,
        ]);
    }
}
