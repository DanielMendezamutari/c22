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
    $turnosDespachados = [];

    foreach ($planillas as $p) {
        if (!$p->turno_id || in_array($p->turno_id, $turnosDespachados)) {
            continue;
        }

        try {
            $this->comment("  -> Generando y enviando informe para Turno #{$p->turno_id} ({$p->sucursal?->nombre})...");
            $res = $despachador->ejecutar($p->turno_id, (bool) $this->option('forzar'));
            $turnosDespachados[] = $p->turno_id;
            $this->info("     ✔ Informe despachado a WhatsApp: {$res['grupo_destino']} | Ventas: Bs {$res['ventas_pos']} | Sobre: Bs {$res['efectivo_sobre']}");
        } catch (\Throwable $e) {
            $this->warn("     ⚠ No se pudo despachar para Turno #{$p->turno_id}: " . $e->getMessage());
        }
    }

    if (empty($turnosDespachados) && $inbounds->isEmpty()) {
        $this->warn("\n[AVISO] No se encontraron fotos de cierre recibidas hoy en la base de datos.");
        $this->line("Si el personal ya envió las fotos a los grupos de WhatsApp antes de conectar el bot:");
        $this->line("👉 Simplemente REENVÍA (forward) las fotos de hoy al grupo de WhatsApp de la casa.");
        $this->line("El bot las detectará en vivo y despachará el informe automáticamente en 20 segundos.");
    }

    $this->info("==========================================================\n");
})->purpose('Auditar y despachar automáticamente todos los comprobantes y planillas de cierre de hoy');
