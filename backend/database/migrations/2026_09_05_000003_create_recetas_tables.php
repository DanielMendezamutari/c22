<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Recetas de Transformación (Rellenos)
        Schema::create('recetas_transformacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insumo_origen_id')->constrained('productos');
            $table->foreignId('producto_destino_id')->constrained('productos');
            $table->decimal('tarifa_comision_unidad', 8, 2)->default(1.00);
            $table->decimal('ratio_referencia_esperado', 6, 3)->default(1.000);
            $table->decimal('umbral_desviacion_alerta', 5, 2)->default(15.00);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['insumo_origen_id', 'producto_destino_id'], 'receta_origen_destino_unique');
        });

        // 2. Recetas de Combos (Desglose automático de Ticket Z)
        Schema::create('recetas_combos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_combo', 150);
            $table->foreignId('producto_terminado_id')->constrained('productos');
            $table->unsignedInteger('unidades_equivalentes');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Vincular FK de movimientos_inventario.receta_id
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreign('receta_id')
                  ->references('id')
                  ->on('recetas_transformacion')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropForeign(['receta_id']);
        });
        Schema::dropIfExists('recetas_combos');
        Schema::dropIfExists('recetas_transformacion');
    }
};
