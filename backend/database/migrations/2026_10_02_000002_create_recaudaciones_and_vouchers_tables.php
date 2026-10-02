<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Planillas físicas manuscritas de caja
        Schema::create('planillas_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->date('fecha_operativa');
            $table->string('foto_url', 500);
            $table->decimal('total_ventas_declaradas_bs', 10, 2)->default(0.00);
            $table->decimal('total_gastos_declarados_bs', 10, 2)->default(0.00);
            $table->decimal('monto_sobre_efectivo_bs', 10, 2)->default(0.00);
            $table->string('cajero_nombre', 150)->nullable();
            $table->json('datos_ocr_json')->nullable();
            $table->enum('estado_ocr', ['pendiente', 'procesado', 'error_lectura', 'corregido_manual'])->default('pendiente');
            $table->foreignId('auditado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha_operativa']);
        });

        // 2. Vouchers físicos de depósito bancario entregados por el recaudador
        Schema::create('vouchers_deposito', function (Blueprint $table) {
            $table->id();
            $table->string('banco_nombre', 100)->default('BANCO');
            $table->string('nro_operacion', 100)->nullable();
            $table->dateTime('fecha_deposito')->nullable();
            $table->decimal('monto_depositado_bs', 10, 2)->default(0.00);
            $table->string('titular_cuenta', 150)->nullable();
            $table->string('foto_url', 500);
            $table->foreignId('recaudador_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->json('datos_ocr_json')->nullable();
            $table->enum('estado_ocr', ['pendiente', 'procesado', 'error_lectura', 'corregido_manual'])->default('pendiente');
            $table->timestamps();

            $table->index('nro_operacion');
        });

        // 3. Recaudaciones diarias consolidadas (Cruce Planilla vs Depósito Bancario)
        Schema::create('recaudaciones_diarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('planilla_id')->nullable()->constrained('planillas_caja')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers_deposito')->nullOnDelete();
            $table->decimal('monto_sobre_declarado_bs', 10, 2)->default(0.00);
            $table->decimal('monto_voucher_banco_bs', 10, 2)->default(0.00);
            $table->decimal('diferencia_bs', 10, 2)->default(0.00); // voucher - sobre (negativo = faltante)
            $table->enum('estado_conciliacion', [
                'conciliado_exacto',
                'discrepancia_faltante',
                'discrepancia_sobrante',
                'pendiente_voucher',
                'observado',
            ])->default('pendiente_voucher');
            $table->boolean('alerta_whatsapp_enviada')->default(false);
            $table->dateTime('alerta_whatsapp_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['sucursal_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recaudaciones_diarias');
        Schema::dropIfExists('vouchers_deposito');
        Schema::dropIfExists('planillas_caja');
    }
};
