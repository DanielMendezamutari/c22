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
        Schema::create('jornales_garzones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->date('fecha');
            $table->decimal('jornal_base_bs', 10, 2)->default(0.00);
            $table->decimal('faltante_botellas_unidades', 8, 2)->default(0.00);
            $table->decimal('descuento_faltante_bs', 10, 2)->default(0.00);
            $table->decimal('total_neto_pagado_bs', 10, 2)->default(0.00);
            $table->string('foto_comprobante_url', 255)->nullable();
            $table->enum('estado', ['pendiente', 'pagado_en_caja', 'anulado'])->default('pendiente');
            $table->timestamps();

            $table->index(['turno_id', 'usuario_id']);
            $table->index(['sucursal_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jornales_garzones');
    }
};
