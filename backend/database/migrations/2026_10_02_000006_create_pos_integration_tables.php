<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar token_acceso a sucursales si no existe
        if (!Schema::hasColumn('sucursales', 'token_acceso')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->string('token_acceso', 100)->nullable()->unique()->after('codigo');
            });

            // Generar tokens para sucursales existentes
            $sucursales = DB::table('sucursales')->get();
            foreach ($sucursales as $s) {
                DB::table('sucursales')->where('id', $s->id)->update([
                    'token_acceso' => 'c22_' . Str::slug($s->codigo ?: $s->nombre, '_') . '_' . Str::random(24)
                ]);
            }
        }

        // 2. Tabla pos_producto_mapeo
        if (!Schema::hasTable('pos_producto_mapeo')) {
            Schema::create('pos_producto_mapeo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->string('pos_producto_id', 50);
                $table->string('pos_nombre_producto', 200);
                $table->foreignId('c22_producto_id')->nullable()->constrained('productos')->nullOnDelete();
                $table->foreignId('c22_combo_id')->nullable()->constrained('recetas_combos')->nullOnDelete();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['sucursal_id', 'pos_producto_id'], 'pos_map_sucursal_prod_unique');
                $table->index(['sucursal_id', 'activo']);
            });
        }

        // 3. Tabla pos_transacciones
        if (!Schema::hasTable('pos_transacciones')) {
            Schema::create('pos_transacciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
                $table->string('pos_detalle_id', 50);
                $table->string('pos_cuenta_id', 50);
                $table->dateTime('fecha_hora');
                $table->string('pos_producto_id', 50);
                $table->string('pos_nombre_producto', 200);
                $table->decimal('cantidad', 8, 2);
                $table->decimal('precio_unitario', 10, 2);
                $table->decimal('subtotal', 10, 2);
                $table->enum('metodo_pago', ['efectivo', 'qr', 'tarjeta', 'mixto', 'otro'])->default('efectivo');
                $table->enum('estado_mapeo', ['mapeado', 'pendiente_mapeo'])->default('pendiente_mapeo');
                $table->timestamps();

                $table->unique(['sucursal_id', 'pos_detalle_id'], 'pos_trans_sucursal_detalle_unique');
                $table->index(['sucursal_id', 'fecha_hora']);
                $table->index(['turno_id', 'estado_mapeo']);
            });
        }

        // 4. Tabla auditorias_conciliacion_triangulada
        Schema::dropIfExists('auditorias_conciliacion_triangulada');
        Schema::create('auditorias_conciliacion_triangulada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->unique()->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->decimal('total_pos_ventas_bs', 12, 2)->default(0.00);
            $table->decimal('total_planilla_efectivo_bs', 12, 2)->default(0.00);
            $table->decimal('total_voucher_deposito_bs', 12, 2)->default(0.00);
            $table->decimal('diferencia_caja_bs', 12, 2)->default(0.00);
            $table->foreignId('responsable_caja_usuario_id')->nullable();
            $table->foreign('responsable_caja_usuario_id', 'fk_act_resp_caja')->references('id')->on('usuarios')->nullOnDelete();
            $table->decimal('botellas_vendidas_pos', 8, 2)->default(0.00);
            $table->decimal('botellas_consumidas_inventario', 8, 2)->default(0.00);
            $table->decimal('diferencia_botellas', 8, 2)->default(0.00);
            $table->foreignId('responsable_barra_usuario_id')->nullable();
            $table->foreign('responsable_barra_usuario_id', 'fk_act_resp_barra')->references('id')->on('usuarios')->nullOnDelete();
            $table->enum('estado_semaforo', ['verde_cuadrado', 'ambar_observado', 'rojo_discrepancia'])->default('verde_cuadrado');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'estado_semaforo'], 'idx_act_sucursal_semaforo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_conciliacion_triangulada');
        Schema::dropIfExists('pos_transacciones');
        Schema::dropIfExists('pos_producto_mapeo');

        if (Schema::hasColumn('sucursales', 'token_acceso')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropColumn('token_acceso');
            });
        }
    }
};
