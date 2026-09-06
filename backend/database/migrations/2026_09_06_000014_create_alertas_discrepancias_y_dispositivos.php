<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Alertas de Discrepancias entre Turnos
        if (!Schema::hasTable('alertas_discrepancias')) {
            Schema::create('alertas_discrepancias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('turno_saliente_id')->nullable()->constrained('turnos')->nullOnDelete();
                $table->foreignId('turno_entrante_id')->constrained('turnos')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->decimal('stock_esperado', 10, 2);   // Cierre del turno anterior
                $table->decimal('stock_declarado', 10, 2);  // Apertura del turno entrante
                $table->decimal('diferencia', 10, 2);       // stock_declarado - stock_esperado (negativo = faltante)
                $table->boolean('resuelto')->default(false);
                $table->text('observaciones')->nullable();
                $table->timestamp('fecha_alerta')->useCurrent();
                $table->timestamps();
            });
        }

        // 2. Tabla de Dispositivos Maestros del Administrador para Push Notifications
        if (!Schema::hasTable('admin_dispositivos')) {
            Schema::create('admin_dispositivos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->string('device_id', 150);
                $table->string('fcm_token', 500)->nullable();
                $table->string('nombre_dispositivo', 100)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamp('ultimo_acceso')->nullable();
                $table->timestamps();

                $table->unique(['usuario_id', 'device_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dispositivos');
        Schema::dropIfExists('alertas_discrepancias');
    }
};
