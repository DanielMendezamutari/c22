<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sucursales (Casas del Grupo Punto Frío)
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('codigo', 20)->unique();
            $table->string('direccion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Usuarios (Personal operativo y administradores)
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->enum('rol', ['barman', 'garzon', 'admin'])->default('barman');
            $table->string('pin_hash', 255);
            $table->enum('modalidad_cobro', ['diario', 'semanal'])->default('diario');
            $table->foreignId('sucursal_actual_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->decimal('saldo_deudor_acumulado', 10, 2)->default(0.00);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Productos (Insumos y terminados)
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('codigo_barra', 50)->nullable();
            $table->enum('tipo', ['insumo', 'terminado', 'ambos']);
            $table->enum('unidad_medida', ['unidad', 'fraccion_cuartos'])->default('unidad');
            $table->boolean('es_transformable')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 4. Turnos (Jornadas de 12h)
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('barman_id')->constrained('usuarios');
            $table->enum('tipo_turno', ['dia', 'noche']);
            $table->enum('estado', ['abierto', 'cobrado', 'cerrado', 'auditado'])->default('abierto');
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->integer('total_transformaciones_netas')->default(0);
            $table->decimal('total_comision_bruta', 10, 2)->default(0.00);
            $table->decimal('total_sancion_descontada', 10, 2)->default(0.00);
            $table->decimal('total_comision_neta_pagada', 10, 2)->default(0.00);
            $table->string('codigo_recibo_cobro', 20)->nullable()->unique();
            $table->dateTime('fecha_cobro')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'estado']);
            $table->index(['barman_id', 'tipo_turno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('sucursales');
    }
};
