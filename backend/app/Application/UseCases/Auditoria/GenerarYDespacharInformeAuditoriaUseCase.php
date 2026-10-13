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

        // Valores financieros extraídos de las fotos de planilla manuscrita y comprobantes de WhatsApp
        $ventasDeclaradas = (float) ($planilla?->total_ventas_declaradas_bs ?? ($datosOcr['total_ventas_declaradas_bs'] ?? $datosOcr['ventas_pos'] ?? 0.00));
        $qrCobrado = (float) ($datosOcr['qr_declarado_bs'] ?? ($datosOcr['ventas_qr_bs'] ?? 0.00));
        $efectivoVentas = (float) ($datosOcr['ventas_efectivo_bs'] ?? max(0, $ventasDeclaradas - $qrCobrado));
        $egresosTotales = (float) ($planilla?->total_gastos_declarados_bs ?? ($datosOcr['total_gastos_declarados_bs'] ?? 0.00));
        $sobreEfectivo = (float) ($planilla?->monto_sobre_efectivo_bs ?? ($datosOcr['monto_sobre_efectivo_bs'] ?? 0.00));

        $pagoChicas = (float) ($datosOcr['pago_chicas_bs'] ?? 0.00);
        $pagoPersonal = (float) ($datosOcr['pago_personal_bs'] ?? ($datosOcr['pago_garzones_bs'] ?? 0.00));
        $gastosOperativos = (float) ($datosOcr['gastos_operativos_bs'] ?? 0.00);

        // Conciliación matemática de Caja: (Ventas en Efectivo - Total Gastos = Sobre Esperado)
        $sobreCalculado = $efectivoVentas - $egresosTotales;
        $diferenciaSobre = $sobreEfectivo - $sobreCalculado;

        // Alertas de cuadre
        $alertasTexto = "";
        if ($sobreEfectivo > 0 && abs($diferenciaSobre) >= 1.00) {
            if ($diferenciaSobre > 0) {
                $alertasTexto .= "\nℹ️ *Sobrante en Sobre:* +Bs " . number_format($diferenciaSobre, 2) . " respecto a ventas y gastos declarados.";
            } else {
                $alertasTexto .= "\n🚨 *ALERTA FALTANTE:* Faltan Bs " . number_format(abs($diferenciaSobre), 2) . " en el sobre respecto a ventas y egresos.";
            }
        } else {
            $alertasTexto .= "\n✅ *Cuadre matemático de caja conforme.*";
        }

        // Construir mensaje estructurado ejecutivo
        $lineas = [
            "📊 *INFORME DE AUDITORÍA Y CIERRE DE CAJA*",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "🏢 *Sede:* {$sucursal->nombre}",
            "📅 *Fecha:* {$fechaTurno} | ⏰ *Turno:* {$tipoTurno}",
            "👤 *Encargada:* {$cajera}",
            "",
            "💰 *INGRESOS DECLARADOS*",
            "• *Total Ventas:* Bs " . number_format($ventasDeclaradas, 2),
            "• *Cobros por QR / Banco:* Bs " . number_format($qrCobrado, 2),
            "• *Ventas en Efectivo:* Bs " . number_format($efectivoVentas, 2),
            "",
            "🧾 *EGRESOS / GASTOS DE CAJA CHICA*",
            "• *Total Egresos Declarados:* Bs " . number_format($egresosTotales, 2),
        ];

        if ($pagoChicas > 0) {
            $lineas[] = "  ↳ _Pago Chicas:_ Bs " . number_format($pagoChicas, 2);
        }
        if ($pagoPersonal > 0) {
            $lineas[] = "  ↳ _Pago Personal / Garzones:_ Bs " . number_format($pagoPersonal, 2);
        }
        if ($gastosOperativos > 0) {
            $lineas[] = "  ↳ _Gastos Operativos / Taxis / Compras:_ Bs " . number_format($gastosOperativos, 2);
        }

        $lineas[] = "";
        $lineas[] = "💵 *EFECTIVO A ENTREGAR*";
        $lineas[] = "• *Sobre Físico Declarado:* Bs " . number_format($sobreEfectivo, 2);
        if ($sobreCalculado > 0) {
            $lineas[] = "• *Sobre Calculado (Efectivo - Gastos):* Bs " . number_format($sobreCalculado, 2);
        }
        $lineas[] = "";
        $lineas[] = "📉 *AUDITORÍA Y CUADRE IA*" . $alertasTexto;
        $lineas[] = "";
        $lineas[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━";
        $lineas[] = "🤖 _Auditoría automática generada desde comprobantes de WhatsApp 24/7_";

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
