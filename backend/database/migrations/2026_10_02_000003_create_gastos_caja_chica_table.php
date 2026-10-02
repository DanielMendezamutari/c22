<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_caja_chica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('planilla_id')->nullable()->constrained('planillas_caja')->nullOnDelete();
            $table->date('fecha');
            $table->string('concepto', 255);
            $table->enum('categoria', [
                'hielo',
                'limpieza',
                'taxi_personal',
                'mantenimiento',
                'insumos_barra',
                'otros',
            ])->default('otros');
            $table->decimal('monto_bs', 10, 2);
            $table->boolean('es_reposicion_de_ventas')->default(true); // Gasto deducido de las ventas en efectivo
            $table->string('foto_comprobante_url', 500)->nullable();
            $table->enum('estado_comprobante', [
                'aprobado_con_foto',
                'observado_sin_comprobante',
                'rechazado',
                'pendiente_revision',
            ])->default('pendiente_revision');
            $table->boolean('emparejado_ocr')->default(false);
            $table->foreignId('aprobado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('aprobado_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha']);
            $table->index('estado_comprobante');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_caja_chica');
    }
};
