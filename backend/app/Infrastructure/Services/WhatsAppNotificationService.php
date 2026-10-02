<?php

namespace App\Infrastructure\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    private string $webhookUrl;
    private string $grupoRecaudacionesJid;

    public function __construct()
    {
        $this->webhookUrl = env('WHATSAPP_BOT_WEBHOOK_URL', '');
        $this->grupoRecaudacionesJid = env('WHATSAPP_GRUPO_RECAUDACIONES', 'grupo-recaudaciones@g.us');
    }

    /**
     * Envía una alerta roja de discrepancia financiera al Grupo de Recaudaciones (Daniel, Dueño y Recaudador).
     */
    public function notificarDiscrepanciaRecaudacion(
        string $sucursalNombre,
        string $fecha,
        float $montoSobre,
        float $montoVoucher,
        float $diferencia,
        string $nroOperacion
    ): bool {
        $tipoDiscrepancia = $diferencia < 0 ? 'FALTANTE DE EFECTIVO 🚨' : 'SOBRANTE DE EFECTIVO ℹ️';
        $diferenciaAbs = abs($diferencia);

        $mensaje = <<<MSG
*🚨 ALERTA DE AUDITORÍA FINANCIERA C22*
*Grupo de Recaudaciones - Punto Frío*
──────────────────────
📍 *Sucursal:* {$sucursalNombre}
📅 *Fecha Operativa:* {$fecha}
📌 *Estado:* {$tipoDiscrepancia}

💵 *Sobre Declarado (Planilla):* Bs. {$montoSobre}
🏦 *Depósito Bancario (Voucher):* Bs. {$montoVoucher}
⚠️ *Diferencia / Brecha:* Bs. {$diferenciaAbs}
📄 *Nro. Operación Bancaria:* {$nroOperacion}
──────────────────────
⚠️ _Verificación automática realizada con Gemini Vision. Por favor revisar el sobre físico y conciliar en la plataforma web: c22.ribersoft.com_
MSG;

        return $this->enviarMensaje($this->grupoRecaudacionesJid, $mensaje);
    }

    /**
     * Envía un mensaje general a un número o grupo de WhatsApp.
     */
    public function enviarMensaje(string $destinatario, string $texto): bool
    {
        Log::info("[WhatsAppNotificationService] Despachando mensaje a {$destinatario}:\n{$texto}");

        if (empty($this->webhookUrl)) {
            Log::info("[WhatsAppNotificationService] WHATSAPP_BOT_WEBHOOK_URL no configurado. Mensaje registrado en logs del sistema.");
            return true;
        }

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, [
                'recipient' => $destinatario,
                'message' => $texto,
                'source' => 'C22_AUDIT_CORE',
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error("[WhatsAppNotificationService] Error enviando WhatsApp: {$e->getMessage()}");
            return false;
        }
    }
}
