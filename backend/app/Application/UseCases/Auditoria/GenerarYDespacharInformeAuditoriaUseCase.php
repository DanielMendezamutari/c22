<?php

namespace App\Application\UseCases\Auditoria;

use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Services\WhatsAppOutboundDispatcherService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\Log;

class GenerarYDespacharInformeAuditoriaUseCase
{
    private WhatsAppOutboundDispatcherService $dispatcher;

    public function __construct(WhatsAppOutboundDispatcherService $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Compila y despacha el informe ejecutivo de auditoría al grupo "Control de Recaudo".
     *
     * @param int $turnoId
     * @param bool $forzarReenvio
     * @return array
     */
    public function ejecutar(int $turnoId, bool $forzarReenvio = false): array
    {
        $turno = Turno::with(['sucursal', 'barman'])->findOrFail($turnoId);
        $sucursal = $turno->sucursal;
        $planilla = PlanillaCaja::where('turno_id', $turnoId)->latest('id')->first();

        // Localizar grupo de destino: primero 'control_recaudo_global', si no hay, grupo global o grupo de la sucursal
        $grupoDestino = SucursalWhatsAppGrupo::where('tipo_auditoria', 'control_recaudo_global')
            ->where('activo', true)
            ->first();

        if (!$grupoDestino) {
            $grupoDestino = SucursalWhatsAppGrupo::where('es_global', true)
                ->where('activo', true)
                ->first();
        }

        if (!$grupoDestino) {
            $grupoDestino = SucursalWhatsAppGrupo::where('sucursal_id', $sucursal->id)
                ->where('activo', true)
                ->first();
        }

        if (!$grupoDestino) {
            throw new DomainException("No se encontró ningún grupo de WhatsApp configurado para 'Control de Recaudo' o para la sucursal {$sucursal->nombre}.");
        }

        $datosOcr = $planilla ? ($planilla->datos_ocr_json ?? []) : [];
        $fechaTurno = $planilla?->fecha_operativa ?? ($turno->fecha_cierre ? $turno->fecha_cierre->toDateString() : now()->toDateString());
        $cajera = $planilla?->cajero_nombre ?? $turno->barman?->nombre ?? 'Encargada';
        $tipoTurno = ucfirst($turno->tipo_turno ?? 'Día');

        // Valores financieros
        $ventasPlanilla = (float) ($planilla?->total_ventas_declaradas_bs ?? 0.00);
        $egresosPlanilla = (float) ($planilla?->total_gastos_declarados_bs ?? 0.00);
        $sobreEfectivo = (float) ($planilla?->monto_sobre_efectivo_bs ?? 0.00);

        // Si tenemos datos extraídos de tickets POS en datos_ocr_json
        $ventasPos = (float) ($datosOcr['ventas_pos'] ?? $datosOcr['total_cobros_bs'] ?? $ventasPlanilla);
        $qrPos = (float) ($datosOcr['ventas_qr_bs'] ?? $datosOcr['qr_pos'] ?? ($datosOcr['qr_declarado_bs'] ?? 0.00));
        $efectivoPos = (float) ($datosOcr['ventas_efectivo_bs'] ?? 0.00);
        $qrPlanilla = (float) ($datosOcr['qr_declarado_bs'] ?? $qrPos);

        // Alertas
        $alertasTexto = "";
        $diferenciaQr = abs($qrPos - $qrPlanilla);
        if ($qrPos > 0 && $qrPlanilla > 0 && $diferenciaQr >= 1.00) {
            $alertasTexto .= "\n🚨 *ALERTA:* Discrepancia en QR (POS: Bs " . number_format($qrPos, 2) . " vs Planilla: Bs " . number_format($qrPlanilla, 2) . ")";
        }

        $diferenciaVentas = $ventasPlanilla - $ventasPos;
        if (abs($diferenciaVentas) >= 1.00) {
            $alertasTexto .= "\n⚠️ *OBSERVACIÓN:* Diferencia en ventas de Bs " . number_format($diferenciaVentas, 2) . " (Manual vs POS)";
        }

        if (empty($alertasTexto)) {
            $alertasTexto = "\n✅ *Conciliación conforme sin discrepancias críticas.*";
        }

        // Construir mensaje estructurado
        $lineas = [
            "📊 *INFORME DE AUDITORÍA Y CIERRE DE TURNO*",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🏢 *Sede:* {$sucursal->nombre}",
            "📅 *Fecha:* {$fechaTurno} | ⏰ *Turno:* {$tipoTurno}",
            "👤 *Cajera:* {$cajera}",
            "",
            "💰 *CONCILIACIÓN DE INGRESOS*",
            "• *Ventas según POS:* Bs " . number_format($ventasPos, 2),
            "• *Cobros por QR (POS):* Bs " . number_format($qrPos, 2),
            "• *Venta Declarada en Planilla:* Bs " . number_format($ventasPlanilla, 2),
            "• *Total Egresos Declarados:* Bs " . number_format($egresosPlanilla, 2),
            "• *💵 Efectivo en Sobre Entregado:* Bs " . number_format($sobreEfectivo, 2),
            "",
            "📉 *ALERTAS Y AUDITORÍA IA*" . $alertasTexto,
            "",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🤖 _Auditoría automática generada por Sistema Casa22 24/7_",
        ];

        $textoMensaje = implode("\n", $lineas);
        $mediaUrl = $planilla?->foto_url ? url($planilla->foto_url) : null;
        $mediaPath = $planilla?->foto_url ?: null;

        $resultado = $this->dispatcher->enviarMensaje(
            $grupoDestino->remote_jid,
            $textoMensaje,
            $mediaUrl,
            'informe_cierre_ejecutivo',
            $sucursal->id,
            $turnoId,
            $mediaPath
        );

        return [
            'success' => $resultado['success'] ?? false,
            'error' => $resultado['error'] ?? null,
            'sucursal' => $sucursal->nombre,
            'grupo_destino' => $grupoDestino->nombre_grupo,
            'remote_jid' => $grupoDestino->remote_jid,
            'ventas_pos' => $ventasPos,
            'qr_pos' => $qrPos,
            'egresos_totales' => $egresosPlanilla,
            'efectivo_sobre' => $sobreEfectivo,
            'alertas_count' => empty($alertasTexto) ? 0 : 1,
            'despachado_at' => now()->toDateTimeString(),
        ];
    }
}
