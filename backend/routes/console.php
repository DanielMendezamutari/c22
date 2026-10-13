<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('whatsapp:auditar-hoy {--forzar : Forzar reenvío de informes ya despachados}', function () {
    $this->info("==========================================================");
    $this->info(" [AUDITORÍA C22] Procesando Cierres y Mensajes de Hoy");
    $this->info("==========================================================");

    $hoy = now()->toDateString();
    $inbounds = \App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound::whereDate('created_at', $hoy)
        ->orWhere('created_at', '>=', now()->subHours(8))
        ->get();

    $this->line("• Mensajes capturados hoy en WhatsApp: " . $inbounds->count());

    $procesados = 0;
    foreach ($inbounds as $msg) {
        if ($msg->media_path && $msg->estado !== 'procesado') {
            $this->comment("  -> Procesando imagen ID #{$msg->id} ({$msg->sender_name})...");
            try {
                \App\Jobs\ProcesarMensajeWhatsAppJob::dispatchSync($msg->id);
                $procesados++;
            } catch (\Throwable $e) {
                $this->error("     Error en ID #{$msg->id}: " . $e->getMessage());
            }
        }
    }

    if ($procesados > 0) {
        $this->info("✔ {$procesados} imágenes analizadas con Gemini Vision OCR.");
    }

    // Buscar planillas de hoy o turnos cerrados hoy
    $planillas = \App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja::whereDate('fecha_operativa', $hoy)
        ->orWhere('updated_at', '>=', now()->subHours(8))
        ->get();

    $this->line("• Planillas registradas para hoy: " . $planillas->count());

    $despachador = app(\App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase::class);
    $outboundService = app(\App\Services\WhatsAppOutboundDispatcherService::class);

    // Diagnóstico en vivo de conexión del Bot Baileys
    $estadoBot = $outboundService->verificarEstadoBot();
    if (!empty($estadoBot['conectado'])) {
        $this->info("• Estado del Bot WhatsApp: CONECTADO (+{$estadoBot['usuario']}) | Grupos monitoreados: {$estadoBot['grupos_registrados']}");
    } else {
        $motivo = $estadoBot['error'] ?? 'Sesión cerrada o esperando escaneo de QR';
        $this->warn("• Estado del Bot WhatsApp: DESCONECTADO ({$motivo})");
        $this->line("  💡 Tip: Verifica el log del bot con: tail -n 25 scripts/whatsapp_bot/bot_salida.log");
    }

    $turnosDespachados = [];

    foreach ($planillas as $p) {
        if (!$p->turno_id || in_array($p->turno_id, $turnosDespachados)) {
            continue;
        }

        try {
            $this->comment("\n  -> Generando y enviando informe para Turno #{$p->turno_id} ({$p->sucursal?->nombre})...");
            $res = $despachador->ejecutar($p->turno_id, (bool) $this->option('forzar'));
            $turnosDespachados[] = $p->turno_id;
            if (!empty($res['success'])) {
                $this->info("     ✔ Informe despachado con éxito a WhatsApp:");
                $this->line("        Grupo: {$res['grupo_destino']} | JID: {$res['remote_jid']}");
                $this->line("        Ventas Declaradas: Bs {$res['ventas_declaradas']} | Efectivo Sobre: Bs {$res['efectivo_sobre']}");
            } else {
                $this->error("     ❌ Falló el envío a WhatsApp: " . ($res['error'] ?? 'El bot no pudo entregar el mensaje'));
                $this->line("        Grupo Destino: {$res['grupo_destino']} | JID: " . ($res['remote_jid'] ?? 'N/A'));
            }
        } catch (\Throwable $e) {
            $this->error("     ❌ Error procesando Turno #{$p->turno_id}: " . $e->getMessage());
        }
    }

    // Resumen de último mensaje outbound
    $ultimoOutbound = \App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeOutbound::latest('id')->first();
    if ($ultimoOutbound) {
        $this->line("\n• Registro Outbound más reciente (#{$ultimoOutbound->id}): Destino={$ultimoOutbound->remote_jid} | Estado={$ultimoOutbound->estado_envio}" . ($ultimoOutbound->error_motivo ? " | Motivo={$ultimoOutbound->error_motivo}" : ""));
    }

    if (empty($turnosDespachados) && $inbounds->isEmpty()) {
        $this->warn("\n[AVISO] No se encontraron fotos de cierre recibidas hoy en la base de datos.");
        $this->line("Si el personal ya envió las fotos a los grupos de WhatsApp antes de conectar el bot:");
        $this->line("👉 Simplemente REENVÍA (forward) las fotos de hoy al grupo de WhatsApp de la casa.");
        $this->line("El bot las detectará en vivo y despachará el informe automáticamente en 20 segundos.");
    }

    $this->info("\n==========================================================\n");
})->purpose('Auditar y despachar automáticamente todos los comprobantes y planillas de cierre de hoy');

Artisan::command('whatsapp:importar-zip {archivo : Ruta del archivo ZIP en el servidor} {--sucursal_id= : ID opcional de la sucursal asignada}', function () {
    $archivo = $this->argument('archivo');
    $sucursalId = $this->option('sucursal_id');

    if (!file_exists($archivo)) {
        $this->error("El archivo no existe: {$archivo}");
        return 1;
    }

    $this->info("==========================================================");
    $this->info(" [IMPORTADOR ZIP C22] Extrayendo comprobantes para Auditoría");
    $this->info("==========================================================");

    $zip = new \ZipArchive();
    if ($zip->open($archivo) !== true) {
        $this->error("No se pudo abrir el archivo ZIP.");
        return 1;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    $extraidas = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $fname = $zip->getNameIndex($i);
        if (str_ends_with($fname, '/') || str_contains($fname, '__MACOSX') || str_starts_with(basename($fname), '.')) continue;
        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) continue;

        $content = $zip->getFromIndex($i);
        if (!$content) continue;

        $extractedName = 'cli_zip_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        \Illuminate\Support\Facades\Storage::disk('public')->put('comprobantes/whatsapp/' . $extractedName, $content);

        $inbound = \App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound::create([
            'sucursal_id' => $sucursalId ?: null,
            'remote_jid' => 'zip_cli@c22.ribersoft.com',
            'sender_phone' => '00000000',
            'sender_name' => 'Importación ZIP CLI (' . basename($fname) . ')',
            'tipo_mensaje' => 'imagen',
            'media_path' => 'storage/comprobantes/whatsapp/' . $extractedName,
            'raw_text' => basename($fname),
            'clasificacion_ia' => 'desconocido',
            'score_confianza' => $sucursalId ? 1.00 : 0.00,
            'estado' => $sucursalId ? 'pendiente_proceso' : 'requiere_confirmacion',
        ]);

        $this->comment("  -> Extraída imagen: " . basename($fname) . " (Inbound #{$inbound->id})");
        try {
            \App\Jobs\ProcesarMensajeWhatsAppJob::dispatchSync($inbound->id);
            $this->info("     ✔ Procesada con Gemini Vision OCR");
        } catch (\Throwable $e) {
            $this->warn("     ⚠ Error en job: " . $e->getMessage());
        }
        $extraidas++;
    }

    $zip->close();
    $this->info("\n✔ Extracción completa: {$extraidas} comprobantes analizados.");
    $this->info("==========================================================\n");
    return 0;
})->purpose('Importar y auditar automáticamente un archivo ZIP con fotos o exportación de chat');
