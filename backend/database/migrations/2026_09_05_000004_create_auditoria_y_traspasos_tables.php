<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Traspasos (Custodia en tránsito entre casas)
        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_origen_id')->constrained('sucursales');
            $table->foreignId('sucursal_destino_id')->constrained('sucursales');
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad_despachada', 8, 2);
            $table->decimal('cantidad_recibida_conforme', 8, 2)->default(0.00);
            $table->decimal('cantidad_merma_transito', 8, 2)->default(0.00);
            $table->enum('estado', [
                'en_transito',
                'recibido_conforme',
                'recibido_con_discrepancia',
                'cancelado'
            ])->default('en_transito');
            $table->foreignId('usuario_emisor_id')->constrained('usuarios');
            $table->foreignId('usuario_receptor_id')->nullable()->constrained('usuarios');
            $table->string('foto_despacho', 255)->nullable();
            $table->string('foto_recepcion', 255)->nullable();
            $table->dateTime('fecha_envio');
            $table->dateTime('fecha_recepcion')->nullable();
            $table->timestamps();

            $table->index(['sucursal_destino_id', 'estado']);
        });

        // 2. Auditorías de Ventas (Ticket Z vs Inventario Físico)
        Schema::create('auditorias_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->unique()->constrained('turnos');
            $table->foreignId('admin_id')->constrained('usuarios');
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('stock_inicial', 8, 2);
            $table->decimal('ingresos', 8, 2)->default(0.00);
            $table->decimal('traspasos_netos', 8, 2)->default(0.00);
            $table->decimal('materia_prima_usada', 8, 2)->default(0.00);
            $table->decimal('producto_terminado', 8, 2)->default(0.00);
            $table->decimal('bajas', 8, 2)->default(0.00);
            $table->decimal('stock_final', 8, 2);
            $table->decimal('consumo_fisico_calculado', 8, 2);
            $table->decimal('ventas_ticket_z', 8, 2);
            $table->decimal('diferencia', 8, 2); // Consumo Físico - Ventas
            $table->enum('resultado', ['cuadrado', 'faltante', 'sobrante']);
            $table->decimal('sancion_monto', 10, 2)->default(0.00);
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha_auditoria');
            $table->timestamps();
        });

        // 3. Sanciones de Inventario (Deudas por faltantes)
        Schema::create('sanciones_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('auditoria_id')->constrained('auditorias_ventas');
            $table->decimal('monto_sancion', 10, 2);
            $table->decimal('monto_descontado', 10, 2)->default(0.00);
            $table->enum('estado', [
                'pendiente',
                'descontado_caja',
                'descontado_semanal',
                'anulado'
            ])->default('pendiente');
            $table->dateTime('fecha_imputacion');
            $table->dateTime('fecha_liquidacion')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'estado']);
        });

        // 4. Liquidaciones Semanales (Barmen Turno Noche)
        Schema::create('liquidaciones_semanales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->string('semana_ano', 10); // ej. '2026-W36'
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('total_turnos');
            $table->decimal('total_comisiones_brutas', 10, 2);
            $table->decimal('total_sanciones_deducidas', 10, 2);
            $table->decimal('total_neto_a_pagar', 10, 2);
            $table->enum('estado', ['borrador', 'aprobado', 'pagado'])->default('borrador');
            $table->timestamps();

            $table->index(['usuario_id', 'semana_ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_semanales');
        Schema::dropIfExists('sanciones_inventario');
        Schema::dropIfExists('auditorias_ventas');
        Schema::dropIfExists('traspasos');
    }
};
