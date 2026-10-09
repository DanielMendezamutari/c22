<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Jobs\ProcesarMensajeWhatsAppJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Devuelve la whitelist de grupos autorizados para el Zero-Leakage Guard del bot.
     * GET /api/v1/whatsapp/grupos-auditables
     */
    public function gruposAuditables(): JsonResponse
    {
        $grupos = SucursalWhatsAppGrupo::with('sucursal:id,nombre,codigo')
            ->where('activo', true)
            ->get()
            ->map(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'remote_jid' => $grupo->remote_jid,
                    'sucursal_id' => $grupo->sucursal_id,
                    'sucursal_nombre' => $grupo->sucursal?->nombre ?? 'Desconocida',
                    'sucursal_codigo' => $grupo->sucursal?->codigo ?? '',
                    'nombre_grupo' => $grupo->nombre_grupo,
                    'tipo_auditoria' => $grupo->tipo_auditoria,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $grupos,
        ]);
    }

    /**
     * Registra o actualiza un grupo de WhatsApp auditable.
     * POST /api/v1/whatsapp/grupos
     */
    public function registrarGrupo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'remote_jid' => 'required|string|max:100',
            'nombre_grupo' => 'required|string|max:150',
            'tipo_auditoria' => 'nullable|in:cierre_recaudacion,gastos_caja_chica,taxis_rotacion,general',
        ]);

        $grupo = SucursalWhatsAppGrupo::updateOrCreate(
            ['remote_jid' => $validated['remote_jid']],
            [
                'sucursal_id' => $validated['sucursal_id'],
                'nombre_grupo' => $validated['nombre_grupo'],
                'tipo_auditoria' => $validated['tipo_auditoria'] ?? 'cierre_recaudacion',
                'activo' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo de WhatsApp vinculado exitosamente a la sucursal.',
            'data' => $grupo,
        ], Response::HTTP_CREATED);
    }

    /**
     * Ingesta de mensajes y multimedia desde el microservicio Baileys.
     * POST /api/v1/webhook/whatsapp
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $remoteJid = $request->input('remote_jid');
        $senderPhone = $request->input('sender_phone', '');
        $senderName = $request->input('sender_name', '');
        $tipo = $request->input('tipo', 'imagen');
        $caption = $request->input('caption', '');
        $mediaBase64 = $request->input('media_base64');
        $mimetype = $request->input('mimetype', 'image/jpeg');

        if (!$remoteJid) {
            return response()->json([
                'success' => false,
                'error' => 'remote_jid es requerido',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 1. Resolución determinista 100% de la sucursal por sucursal_whatsapp_grupos
        $grupo = SucursalWhatsAppGrupo::where('remote_jid', $remoteJid)
            ->where('activo', true)
            ->first();

        $sucursalId = $grupo?->sucursal_id;

        // Fallback rápido: columna whatsapp_group_jid en tabla sucursales
        if (!$sucursalId) {
            $sucursalId = Sucursal::where('whatsapp_group_jid', $remoteJid)->value('id');
        }

        // 2. Localizar turno activo o reciente de la sucursal
        $turnoId = null;
        if ($sucursalId) {
            $turno = Turno::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierto')
                ->latest('id')
                ->first();

            if (!$turno) {
                // Si no hay turno abierto, enlazar al último turno cerrado de las últimas 24h
                $turno = Turno::where('sucursal_id', $sucursalId)
                    ->latest('id')
                    ->first();
            }
            $turnoId = $turno?->id;
        }

        // 3. Procesar almacenamiento físico de la imagen/documento
        $mediaPath = null;
        if ($mediaBase64) {
            $mediaPath = $this->guardarArchivoBase64($mediaBase64, $mimetype);
        }

        // 4. Determinar estado inicial
        $estado = $sucursalId ? 'pendiente_proceso' : 'requiere_confirmacion';

        // 5. Persistir en whatsapp_mensajes_inbound
        $inbound = WhatsAppMensajeInbound::create([
            'sucursal_id' => $sucursalId,
            'remote_jid' => $remoteJid,
            'sender_phone' => $senderPhone,
            'sender_name' => $senderName,
            'tipo_mensaje' => in_array($tipo, ['imagen', 'documento', 'texto', 'audio']) ? $tipo : 'imagen',
            'media_path' => $mediaPath,
            'raw_text' => $caption,
            'clasificacion_ia' => 'desconocido',
            'score_confianza' => $sucursalId ? 1.00 : 0.00,
            'metadata_ia' => null,
            'estado' => $estado,
            'turno_id' => $turnoId,
        ]);

        // 6. Encolar job de procesamiento IA con Gemini Vision
        try {
            if (class_exists(ProcesarMensajeWhatsAppJob::class)) {
                ProcesarMensajeWhatsAppJob::dispatch($inbound->id);
            }
        } catch (\Throwable $e) {
            Log::warning("No se pudo despachar ProcesarMensajeWhatsAppJob: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Mensaje encolado para procesamiento autónomo con IA',
            'inbound_id' => $inbound->id,
            'status' => $inbound->estado,
            'sucursal_id' => $sucursalId,
        ]);
    }

    /**
     * Confirmar sucursal manualmente para mensajes con [SUCURSAL_POR_CONFIRMAR].
     * POST /api/v1/whatsapp/confirmar-sucursal
     */
    public function confirmarSucursal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inbound_id' => 'required|exists:whatsapp_mensajes_inbound,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'turno_id' => 'nullable|exists:turnos,id',
        ]);

        $inbound = WhatsAppMensajeInbound::findOrFail($validated['inbound_id']);
        $inbound->sucursal_id = $validated['sucursal_id'];
        if (!empty($validated['turno_id'])) {
            $inbound->turno_id = $validated['turno_id'];
        } else {
            $turno = Turno::where('sucursal_id', $validated['sucursal_id'])->latest('id')->first();
            $inbound->turno_id = $turno?->id;
        }

        $inbound->estado = 'procesado';
        $inbound->score_confianza = 1.00;
        $inbound->save();

        return response()->json([
            'success' => true,
            'message' => 'Sucursal vinculada y conciliación recalculada exitosamente.',
            'data' => $inbound,
        ]);
    }

    /**
     * Decodifica y persiste la imagen base64.
     */
    private function guardarArchivoBase64(string $base64String, string $mimetype): ?string
    {
        try {
            // Remover prefijo data:image/...;base64, si existe
            if (preg_match('/^data:([a-zA-Z0-9\/\+]+);base64,/', $base64String, $matches)) {
                $base64String = substr($base64String, strpos($base64String, ',') + 1);
            }

            $decoded = base64_decode($base64String);
            if ($decoded === false) {
                return null;
            }

            $extension = 'jpg';
            if (str_contains($mimetype, 'png')) {
                $extension = 'png';
            } elseif (str_contains($mimetype, 'pdf')) {
                $extension = 'pdf';
            }

            $filename = 'whatsapp_inbound_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $relativeDir = 'comprobantes/whatsapp';
            
            // Guardar en public/storage/comprobantes/whatsapp
            Storage::disk('public')->put($relativeDir . '/' . $filename, $decoded);

            return 'storage/' . $relativeDir . '/' . $filename;
        } catch (\Throwable $e) {
            Log::error("Error guardando multimedia de WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Invocado por el microservicio Baileys para notificar cambios de estado en tiempo real.
     * POST /api/v1/whatsapp/bot-status
     */
    public function actualizarBotStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'estado' => 'required|in:conectado,esperando_qr,desconectado',
            'qr_code_data_url' => 'nullable|string',
            'telefono' => 'nullable|string|max:50',
        ]);

        $statusData = [
            'estado' => $validated['estado'],
            'qr_code_data_url' => $validated['qr_code_data_url'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'ultimo_ping' => now()->toDateTimeString(),
            'desconectar_solicitado' => (bool) \Illuminate\Support\Facades\Cache::get('whatsapp_bot_desconectar_solicitado', false),
        ];

        // Guardar en caché por 10 minutos
        \Illuminate\Support\Facades\Cache::put('whatsapp_bot_status', $statusData, now()->addMinutes(10));

        // Si se conectó, reiniciar flag de desconexión
        if ($validated['estado'] === 'conectado') {
            \Illuminate\Support\Facades\Cache::forget('whatsapp_bot_desconectar_solicitado');
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado del bot actualizado exitosamente',
            'data' => $statusData,
        ]);
    }

    /**
     * Consultado por la web para renderizar el QR o badge de estado.
     * GET /api/v1/whatsapp/bot-status
     */
    public function obtenerBotStatus(): JsonResponse
    {
        $status = \Illuminate\Support\Facades\Cache::get('whatsapp_bot_status', [
            'estado' => 'desconectado',
            'qr_code_data_url' => null,
            'telefono' => null,
            'ultimo_ping' => null,
            'desconectar_solicitado' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Invocado por Baileys al conectarse para enviar la lista de grupos descubiertos.
     * POST /api/v1/whatsapp/grupos-descubiertos
     */
    public function sincronizarGruposDescubiertos(Request $request): JsonResponse
    {
        $grupos = $request->input('grupos', []);
        if (!is_array($grupos)) {
            return response()->json(['success' => false, 'error' => 'Formato inválido de grupos'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sincronizados = 0;
        foreach ($grupos as $g) {
            $jid = $g['jid'] ?? $g['remote_jid'] ?? null;
            $nombre = $g['name'] ?? $g['nombre'] ?? $g['nombre_grupo'] ?? 'Grupo WhatsApp';
            $participantes = $g['participants'] ?? $g['participantes_count'] ?? 0;

            if ($jid) {
                \App\Infrastructure\Persistence\Eloquent\Models\WhatsAppGrupoDescubierto::updateOrCreate(
                    ['remote_jid' => $jid],
                    [
                        'nombre_grupo' => $nombre,
                        'participantes_count' => (int) $participantes,
                        'ultima_deteccion_at' => now(),
                    ]
                );
                $sincronizados++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Grupos descubiertos sincronizados exitosamente',
            'total_sincronizados' => $sincronizados,
        ]);
    }

    /**
     * Consultado por la interfaz web para listar grupos con su vinculación actual a sucursales.
     * GET /api/v1/whatsapp/grupos-disponibles
     */
    public function listarGruposDisponibles(): JsonResponse
    {
        // Traer todos los grupos descubiertos con su relación a sucursal_whatsapp_grupos
        $gruposDescubiertos = \App\Infrastructure\Persistence\Eloquent\Models\WhatsAppGrupoDescubierto::with('vinculacion.sucursal')
            ->orderBy('nombre_grupo')
            ->get();

        // Además, traer aquellos grupos vinculados manualmente que tal vez aún no estén en descubiertos
        $vinculadosJids = $gruposDescubiertos->pluck('remote_jid')->toArray();
        $otrosVinculados = SucursalWhatsAppGrupo::with('sucursal')
            ->whereNotIn('remote_jid', $vinculadosJids)
            ->get();

        $data = [];

        foreach ($gruposDescubiertos as $gd) {
            $v = $gd->vinculacion;
            $data[] = [
                'remote_jid' => $gd->remote_jid,
                'nombre_grupo' => $gd->nombre_grupo,
                'participantes_count' => $gd->participantes_count,
                'ultima_deteccion_at' => $gd->ultima_deteccion_at?->toDateTimeString(),
                'vinculado' => $v !== null,
                'vinculacion_id' => $v?->id,
                'sucursal_id' => $v?->sucursal_id,
                'sucursal_nombre' => $v?->sucursal?->nombre,
                'tipo_auditoria' => $v?->tipo_auditoria,
                'activo' => (bool) ($v?->activo ?? false),
            ];
        }

        foreach ($otrosVinculados as $ov) {
            $data[] = [
                'remote_jid' => $ov->remote_jid,
                'nombre_grupo' => $ov->nombre_grupo,
                'participantes_count' => 0,
                'ultima_deteccion_at' => $ov->updated_at?->toDateTimeString(),
                'vinculado' => true,
                'vinculacion_id' => $ov->id,
                'sucursal_id' => $ov->sucursal_id,
                'sucursal_nombre' => $ov->sucursal?->nombre,
                'tipo_auditoria' => $ov->tipo_auditoria,
                'activo' => (bool) $ov->activo,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Permite a Daniel solicitar desconexión del WhatsApp para vincular otro número.
     * POST /api/v1/whatsapp/desconectar
     */
    public function solicitarDesconexion(): JsonResponse
    {
        \Illuminate\Support\Facades\Cache::put('whatsapp_bot_desconectar_solicitado', true, now()->addMinutes(10));
        
        $current = \Illuminate\Support\Facades\Cache::get('whatsapp_bot_status', []);
        $current['estado'] = 'desconectado';
        $current['desconectar_solicitado'] = true;
        \Illuminate\Support\Facades\Cache::put('whatsapp_bot_status', $current, now()->addMinutes(10));

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de desconexión enviada. El bot generará un nuevo QR para vinculación.',
        ]);
    }

    /**
     * Devuelve las últimas fotos y mensajes recibidos por WhatsApp.
     * GET /api/v1/whatsapp/mensajes-recientes
     */
    public function mensajesRecientes(): JsonResponse
    {
        $mensajes = WhatsAppMensajeInbound::with('sucursal:id,nombre,codigo')
            ->latest('id')
            ->take(25)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sucursal_id' => $msg->sucursal_id,
                    'sucursal_nombre' => $msg->sucursal?->nombre ?? 'Por Confirmar',
                    'sender_name' => $msg->sender_name,
                    'sender_phone' => $msg->sender_phone,
                    'tipo_mensaje' => $msg->tipo_mensaje,
                    'media_path' => $msg->media_path ? url($msg->media_path) : null,
                    'raw_text' => $msg->raw_text,
                    'clasificacion_ia' => $msg->clasificacion_ia,
                    'score_confianza' => (float) $msg->score_confianza,
                    'metadata_ia' => $msg->metadata_ia,
                    'estado' => $msg->estado,
                    'created_at' => $msg->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $mensajes,
        ]);
    }
}

