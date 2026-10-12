<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ampliar enum tipo_auditoria en sucursal_whatsapp_grupos si la base de datos lo soporta o es MySQL
        if (Schema::hasTable('sucursal_whatsapp_grupos')) {
            try {
                DB::statement("ALTER TABLE sucursal_whatsapp_grupos MODIFY COLUMN tipo_auditoria ENUM('cierre_recaudacion', 'gastos_caja_chica', 'taxis_rotacion', 'general', 'control_recaudo_global') NOT NULL DEFAULT 'cierre_recaudacion'");
            } catch (\Throwable $e) {
                // SQLite o fallback seguro
            }
        }

        // 2. Crear tabla whatsapp_mensajes_outbound
        if (!Schema::hasTable('whatsapp_mensajes_outbound')) {
            Schema::create('whatsapp_mensajes_outbound', function (Blueprint $table) {
                $table->id();
                $table->string('remote_jid', 100);
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
                $table->enum('tipo_mensaje', [
                    'informe_cierre_ejecutivo',
                    'alerta_discrepancia',
                    'resumen_diario_consolidado',
                    'texto_simple'
                ])->default('informe_cierre_ejecutivo');
                $table->longText('contenido_texto');
                $table->string('media_url', 255)->nullable();
                $table->enum('estado_envio', ['pendiente', 'enviado', 'fallido'])->default('pendiente');
                $table->string('whatsapp_message_id', 100)->nullable();
                $table->text('error_motivo')->nullable();
                $table->timestamp('despachado_at')->nullable();
                $table->timestamps();

                $table->index(['remote_jid', 'estado_envio']);
                $table->index(['turno_id', 'tipo_mensaje']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes_outbound');
    }
};
