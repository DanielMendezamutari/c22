<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_cierre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id');
            $table->date('fecha_operativa');
            $table->enum('tipo_turno', ['dia', 'noche']);
            $table->string('encargada', 150)->nullable();
            $table->json('remitentes')->nullable();
            $table->enum('estado', ['abierto', 'listo', 'conciliado'])->default('abierto');
            $table->dateTime('ultimo_documento_at')->nullable();
            $table->json('resultado')->nullable();
            $table->dateTime('conciliado_at')->nullable();
            $table->timestamps();

            $table->foreign('sucursal_id', 'lotes_cierre_sucursal_fk')->references('id')->on('sucursales');
            $table->unique(['sucursal_id', 'fecha_operativa', 'tipo_turno'], 'lotes_cierre_casa_fecha_turno_uq');
        });

        Schema::create('documentos_cierre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_id');
            $table->foreignId('lote_id')->nullable();
            $table->string('tipo', 40);
            $table->json('datos');
            $table->decimal('confianza', 3, 2);
            $table->foreignId('sucursal_id')->nullable();
            $table->date('fecha_operativa')->nullable();
            $table->enum('tipo_turno', ['dia', 'noche'])->nullable();
            $table->enum('evidencia_casa', ['encabezado', 'grupo', 'encargada', 'remitente', 'texto'])->nullable();
            $table->timestamps();

            $table->foreign('inbound_id', 'documentos_cierre_inbound_fk')->references('id')->on('whatsapp_mensajes_inbound');
            $table->foreign('lote_id', 'documentos_cierre_lote_fk')->references('id')->on('lotes_cierre')->nullOnDelete();
            $table->foreign('sucursal_id', 'documentos_cierre_sucursal_fk')->references('id')->on('sucursales')->nullOnDelete();
            $table->index('lote_id', 'documentos_cierre_lote_idx');
            $table->index(['sucursal_id', 'fecha_operativa', 'tipo_turno'], 'documentos_cierre_casa_fecha_turno_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_cierre');
        Schema::dropIfExists('lotes_cierre');
    }
};
