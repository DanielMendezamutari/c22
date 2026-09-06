<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cortes de Inventario (Conteo físico inicial y final de 12h)
        Schema::create('cortes_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->enum('tipo_corte', ['inicial', 'final']);
            $table->decimal('cantidad', 8, 2); // Soporta fracciones exactas (0.25, 0.50, 0.75, etc.)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['turno_id', 'tipo_corte']);
        });

        // 2. Movimientos de Inventario (Bitácora inmutable)
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_local')->unique();
            $table->foreignId('turno_id')->constrained('turnos');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('producto_id')->constrained('productos');
            $table->enum('tipo_movimiento', [
                'ingreso',
                'transformacion_consumo',
                'transformacion_produccion',
                'baja_rotura',
                'traspaso_salida',
                'traspaso_entrada',
                'merma_transito'
            ]);
            $table->decimal('cantidad', 8, 2);
            $table->string('foto_path', 255)->nullable();
            $table->unsignedBigInteger('receta_id')->nullable();
            $table->decimal('ratio_calculado', 6, 3)->nullable();
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha_movimiento');
            $table->timestamps();

            $table->index(['turno_id', 'tipo_movimiento']);
            $table->index(['sucursal_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('cortes_inventario');
    }
};
