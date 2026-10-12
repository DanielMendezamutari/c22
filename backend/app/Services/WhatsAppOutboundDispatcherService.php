<?php

namespace App\Services;

use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeOutbound;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppOutboundDispatcherService
{
    private string $botHttpUrl;
    private string $internalSecret;

    public function __construct()
    {
        $port = env('BOT_HTTP_PORT', 3001);
        $this->botHttpUrl = "http://127.0.0.1:{$port}";
        $this->internalSecret = env('WHATSAPP_WEBHOOK_SECRET', 'puntofrio_wh_secret_2026_super');
    }

    /**
     * Despacha un mensaje de texto formateado o archivo a un remote_jid de WhatsApp.
     *
     * @param string $remoteJid
     * @param string $texto
     * @param string|null $mediaUrl
     * @param string $tipoMensaje
     * @param int|null $sucursalId
     * @param int|null $turnoId
     * @return array
     */
    public function enviarMensaje(
        string $remoteJid,
        string $texto,
        ?string $mediaUrl = null,
        string $tipoMensaje = 'informe_cierre_ejecutivo',
        ?int $sucursalId = null,
        ?int $turnoId = null
    ): array {
        $outbound = WhatsAppMensajeOutbound::create([
            'remote_jid' => $remoteJid,
            'sucursal_id' => $sucursalId,
            'turno_id' => $turnoId,
            'tipo_mensaje' => $tipoMensaje,
            'contenido_texto' => $texto,
            'media_url' => $mediaUrl,
            'estado_envio' => 'pendiente',
        ]);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Internal-Secret' => $this->internalSecret,
                ])
                ->post("{$this->botHttpUrl}/enviar-mensaje", [
                    'remote_jid' => $remoteJid,
                    'texto' => $texto,
                    'media_url' => $mediaUrl,
                ]);

            if ($response->successful() && $response->json('success')) {
                $msgId = $response->json('messageId');
                $outbound->estado_envio = 'enviado';
                $outbound->whatsapp_message_id = $msgId;
                $outbound->despachado_at = now();
                $outbound->save();

                Log::info("WhatsAppOutbound: Mensaje despachado a {$remoteJid} (ID: {$msgId})");

                return [
                    'success' => true,
                    'outbound_id' => $outbound->id,
                    'message_id' => $msgId,
                ];
            } else {
                $error = $response->json('error') ?? $response->body();
                $outbound->estado_envio = 'fallido';
                $outbound->error_motivo = $error;
                $outbound->save();

                Log::warning("WhatsAppOutbound Fallo: {$error}");

                return [
                    'success' => false,
                    'outbound_id' => $outbound->id,
                    'error' => $error,
                ];
            }
        } catch (\Throwable $e) {
            $outbound->estado_envio = 'fallido';
            $outbound->error_motivo = $e->getMessage();
            $outbound->save();

            Log::error("WhatsAppOutbound Exception: " . $e->getMessage());

            return [
                'success' => false,
                'outbound_id' => $outbound->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Solicita reconexión del bot mediante llamada al microservicio Baileys.
     */
    public function solicitarReconexion(): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Internal-Secret' => $this->internalSecret,
                ])
                ->post("{$this->botHttpUrl}/reconectar");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("WhatsAppOutbound Reconexion Error: " . $e->getMessage());
            return false;
        }
    }
}
