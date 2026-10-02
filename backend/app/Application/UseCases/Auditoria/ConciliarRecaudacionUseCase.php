<?php

namespace App\Application\UseCases\Auditoria;

use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\RecaudacionDiaria;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\VoucherDeposito;
use App\Infrastructure\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class ConciliarRecaudacionUseCase
{
    private WhatsAppNotificationService $whatsAppService;

    public function __construct(WhatsAppNotificationService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function ejecutar(int $sucursalId, string $fecha, ?int $planillaId = null, ?int $voucherId = null): array
    {
        return DB::transaction(function () use ($sucursalId, $fecha, $planillaId, $voucherId) {
            $sucursal = Sucursal::findOrFail($sucursalId);
            $fechaObj = Carbon::parse($fecha)->toDateString();

            // Buscar o crear la conciliación del día para la sucursal
            $recaudacion = RecaudacionDiaria::firstOrNew([
                'sucursal_id' => $sucursalId,
                'fecha' => $fechaObj,
            ]);

            // Asignar planilla si se provee o si ya existe
            if ($planillaId) {
                $recaudacion->planilla_id = $planillaId;
            } elseif (!$recaudacion->planilla_id) {
                $planillaExistente = PlanillaCaja::where('sucursal_id', $sucursalId)
                    ->where('fecha_operativa', $fechaObj)
                    ->latest('id')
                    ->first();
                if ($planillaExistente) {
                    $recaudacion->planilla_id = $planillaExistente->id;
                }
            }

            // Asignar voucher si se provee
            if ($voucherId) {
                $recaudacion->voucher_id = $voucherId;
            }

            // Cargar modelos
            $planilla = $recaudacion->planilla_id ? PlanillaCaja::find($recaudacion->planilla_id) : null;
            $voucher = $recaudacion->voucher_id ? VoucherDeposito::find($recaudacion->voucher_id) : null;

            $montoSobre = $planilla ? (float) $planilla->monto_sobre_efectivo_bs : 0.00;
            $montoVoucher = $voucher ? (float) $voucher->monto_depositado_bs : 0.00;

            $recaudacion->monto_sobre_declarado_bs = $montoSobre;
            $recaudacion->monto_voucher_banco_bs = $montoVoucher;

            if (!$voucher) {
                $recaudacion->estado_conciliacion = 'pendiente_voucher';
                $recaudacion->diferencia_bs = -$montoSobre;
            } else {
                $diferencia = round($montoVoucher - $montoSobre, 2);
                $recaudacion->diferencia_bs = $diferencia;

                if (abs($diferencia) < 0.01) {
                    $recaudacion->estado_conciliacion = 'conciliado_exacto';
                } elseif ($diferencia < 0) {
                    $recaudacion->estado_conciliacion = 'discrepancia_faltante';

                    // Si hay faltante y no se ha notificado aún, disparar alerta automática al grupo de WhatsApp
                    if (!$recaudacion->alerta_whatsapp_enviada) {
                        $enviado = $this->whatsAppService->notificarDiscrepanciaRecaudacion(
                            $sucursal->nombre,
                            $fechaObj,
                            $montoSobre,
                            $montoVoucher,
                            $diferencia,
                            $voucher->nro_operacion ?? 'S/N'
                        );

                        if ($enviado) {
                            $recaudacion->alerta_whatsapp_enviada = true;
                            $recaudacion->alerta_whatsapp_at = Carbon::now();
                        }
                    }
                } else {
                    $recaudacion->estado_conciliacion = 'discrepancia_sobrante';
                }
            }

            $recaudacion->save();

            return [
                'recaudacion_id' => $recaudacion->id,
                'sucursal' => $sucursal->nombre,
                'fecha' => $recaudacion->fecha,
                'monto_sobre_declarado_bs' => (float) $recaudacion->monto_sobre_declarado_bs,
                'monto_voucher_banco_bs' => (float) $recaudacion->monto_voucher_banco_bs,
                'diferencia_bs' => (float) $recaudacion->diferencia_bs,
                'estado_conciliacion' => $recaudacion->estado_conciliacion,
                'alerta_whatsapp_enviada' => (bool) $recaudacion->alerta_whatsapp_enviada,
                'planilla' => $planilla ? [
                    'id' => $planilla->id,
                    'foto_url' => $planilla->foto_url,
                    'total_ventas_declaradas_bs' => (float) $planilla->total_ventas_declaradas_bs,
                    'total_gastos_declarados_bs' => (float) $planilla->total_gastos_declarados_bs,
                    'cajero_nombre' => $planilla->cajero_nombre,
                ] : null,
                'voucher' => $voucher ? [
                    'id' => $voucher->id,
                    'banco_nombre' => $voucher->banco_nombre,
                    'nro_operacion' => $voucher->nro_operacion,
                    'foto_url' => $voucher->foto_url,
                ] : null,
            ];
        });
    }
}
