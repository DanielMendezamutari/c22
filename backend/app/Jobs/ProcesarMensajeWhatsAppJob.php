<?php

namespace App\Jobs;

use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Services\GeminiVisionAuditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarMensajeWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $inboundId;

    public function __construct(int $inboundId)
    {
        $this->inboundId = $inboundId;
    }

    public function handle(GeminiVisionAuditorService $visionAuditor): void
    {
        $inbound = WhatsAppMensajeInbound::find($this->inboundId);
        if (!$inbound) {
            Log::warning("ProcesarMensajeWhatsAppJob: Inbound ID {$this->inboundId} no encontrado.");
            return;
        }

        if (empty($inbound->media_path)) {
            $inbound->estado = 'procesado';
            $inbound->save();
            return;
        }

        try {
            $resultado = $visionAuditor->auditarYClasificar($inbound->media_path);

            $inbound->clasificacion_ia = in_array($resultado['clasificacion'], [
                'planilla_caja', 'voucher_deposito', 'recibo_gasto', 'otro'
            ]) ? $resultado['clasificacion'] : 'otro';
            $inbound->score_confianza = $resultado['score_confianza'];
            $inbound->metadata_ia = $resultado['datos'];

            // Si aún no tenía sucursal asignada, intentar inferirla de sucursal_sugerida
            if (!$inbound->sucursal_id && !empty($resultado['sucursal_sugerida'])) {
                $sucursal = Sucursal::where('nombre', 'LIKE', '%' . $resultado['sucursal_sugerida'] . '%')
                    ->orWhere('codigo', 'LIKE', '%' . $resultado['sucursal_sugerida'] . '%')
                    ->first();
                if ($sucursal) {
                    $inbound->sucursal_id = $sucursal->id;
                }
            }

            // Asociar turno si tenemos sucursal y aún no hay turno
            if ($inbound->sucursal_id && !$inbound->turno_id) {
                $turno = Turno::where('sucursal_id', $inbound->sucursal_id)
                    ->latest('id')
                    ->first();
                $inbound->turno_id = $turno?->id;
            }

            // Si es planilla de caja, persistir o actualizar en planillas_caja para alimentar la Conciliación Triangulada
            if ($inbound->clasificacion_ia === 'planilla_caja' && $inbound->turno_id) {
                $datos = $resultado['datos'] ?? [];
                PlanillaCaja::updateOrCreate(
                    ['turno_id' => $inbound->turno_id],
                    [
                        'sucursal_id' => $inbound->sucursal_id,
                        'fecha_operativa' => $datos['fecha'] ?? now()->toDateString(),
                        'foto_url' => $inbound->media_path,
                        'total_ventas_declaradas_bs' => $datos['total_ventas_declaradas_bs'] ?? 0.00,
                        'total_gastos_declarados_bs' => $datos['total_gastos_declarados_bs'] ?? 0.00,
                        'monto_sobre_efectivo_bs' => $datos['monto_sobre_efectivo_bs'] ?? 0.00,
                        'cajero_nombre' => $datos['cajero_nombre'] ?? $inbound->sender_name,
                        'datos_ocr_json' => $datos,
                        'estado_ocr' => 'procesado',
                        'observaciones' => $datos['observaciones'] ?? 'Procesado automáticamente vía WhatsApp Bot y Gemini Vision',
                    ]
                );
            }

            $inbound->estado = $inbound->sucursal_id ? 'procesado' : 'requiere_confirmacion';
            $inbound->save();

            Log::info("ProcesarMensajeWhatsAppJob: Inbound {$inbound->id} clasificado como {$inbound->clasificacion_ia} (confianza: {$inbound->score_confianza})");
        } catch (\Throwable $e) {
            Log::error("ProcesarMensajeWhatsAppJob Error: " . $e->getMessage());
            $inbound->estado = 'error';
            $inbound->save();
        }
    }
}
