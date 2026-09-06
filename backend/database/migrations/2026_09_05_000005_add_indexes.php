<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->index(['sucursal_id', 'turno_id'], 'idx_movimientos_sucursal_turno');
            $table->index(['tipo_movimiento', 'fecha_movimiento'], 'idx_movimientos_tipo_fecha');
        });

        Schema::table('traspasos', function (Blueprint $table) {
            $table->index(['sucursal_origen_id', 'estado'], 'idx_traspasos_origen_estado');
            $table->index(['fecha_envio'], 'idx_traspasos_fecha_envio');
        });

        Schema::table('turnos', function (Blueprint $table) {
            $table->index(['sucursal_id', 'fecha_apertura'], 'idx_turnos_sucursal_apertura');
        });

        Schema::table('auditorias_ventas', function (Blueprint $table) {
            $table->index(['producto_id', 'fecha_auditoria'], 'idx_auditorias_producto_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropIndex('idx_movimientos_sucursal_turno');
            $table->dropIndex('idx_movimientos_tipo_fecha');
        });

        Schema::table('traspasos', function (Blueprint $table) {
            $table->dropIndex('idx_traspasos_origen_estado');
            $table->dropIndex('idx_traspasos_fecha_envio');
        });

        Schema::table('turnos', function (Blueprint $table) {
            $table->dropIndex('idx_turnos_sucursal_apertura');
        });

        Schema::table('auditorias_ventas', function (Blueprint $table) {
            $table->dropIndex('idx_auditorias_producto_fecha');
        });
    }
};
