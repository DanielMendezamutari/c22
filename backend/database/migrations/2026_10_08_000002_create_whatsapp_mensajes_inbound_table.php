<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_mensajes_inbound', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('remote_jid', 100);
            $table->string('sender_phone', 50);
            $table->string('sender_name', 150)->nullable();
            $table->enum('tipo_mensaje', ['imagen', 'documento', 'texto', 'audio'])->default('imagen');
            $table->string('media_path', 255)->nullable();
            $table->text('raw_text')->nullable();
            $table->enum('clasificacion_ia', [
                'planilla_caja',
                'voucher_deposito',
                'recibo_gasto',
                'otro',
                'desconocido'
            ])->default('desconocido');
            $table->decimal('score_confianza', 5, 2)->default(0.00);
            $table->json('metadata_ia')->nullable();
            $table->enum('estado', [
                'pendiente_proceso',
                'procesado',
                'requiere_confirmacion',
                'error'
            ])->default('pendiente_proceso');
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->timestamps();

            $table->index(['sucursal_id', 'estado']);
            $table->index('remote_jid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes_inbound');
    }
};
