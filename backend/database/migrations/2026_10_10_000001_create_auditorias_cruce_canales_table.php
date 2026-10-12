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
        // 1. Modificar sucursal_whatsapp_grupos para permitir grupos globales multi-sucursal
        if (Schema::hasTable('sucursal_whatsapp_grupos')) {
            Schema::table('sucursal_whatsapp_grupos', function (Blueprint $table) {
                // Hacer sucursal_id nullable si no lo es
                $table->foreignId('sucursal_id')->nullable()->change();
                if (!Schema::hasColumn('sucursal_whatsapp_grupos', 'es_global')) {
                    $table->boolean('es_global')->default(false)->after('tipo_auditoria');
                }
            });
        }

        // 2. Crear tabla auditorias_cruce_canales
        if (!Schema::hasTable('auditorias_cruce_canales')) {
            Schema::create('auditorias_cruce_canales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mensaje_local_inbound_id')->nullable()->constrained('whatsapp_mensajes_inbound')->nullOnDelete();
                $table->foreignId('mensaje_global_inbound_id')->nullable()->constrained('whatsapp_mensajes_inbound')->nullOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
                $table->enum('tipo_evento', ['planilla_cierre', 'gasto_caja_chica', 'voucher_banco'])->default('planilla_cierre');
                $table->decimal('monto_declarado_local', 10, 2)->nullable();
                $table->decimal('monto_declarado_global', 10, 2)->nullable();
                $table->decimal('diferencia_bs', 10, 2)->default(0.00);
                $table->enum('estado_cruce', [
                    'conforme_doble_canal',
                    'discrepancia_detectada',
                    'solo_canal_local',
                    'solo_canal_global',
                    'justificado'
                ])->default('conforme_doble_canal');
                $table->decimal('monto_definitivo_auditado', 10, 2)->nullable();
                $table->string('imputar_responsable', 50)->nullable();
                $table->string('hash_imagen', 64)->nullable();
                $table->text('observaciones')->nullable();
                $table->foreignId('revisado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamps();

                $table->index(['sucursal_id', 'tipo_evento', 'estado_cruce']);
                $table->index('hash_imagen');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias_cruce_canales');

        if (Schema::hasTable('sucursal_whatsapp_grupos')) {
            Schema::table('sucursal_whatsapp_grupos', function (Blueprint $table) {
                if (Schema::hasColumn('sucursal_whatsapp_grupos', 'es_global')) {
                    $table->dropColumn('es_global');
                }
            });
        }
    }
};
