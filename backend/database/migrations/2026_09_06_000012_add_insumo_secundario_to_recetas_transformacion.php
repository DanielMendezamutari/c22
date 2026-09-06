<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_transformacion', function (Blueprint $table) {
            $table->string('nombre', 150)->nullable()->after('id');
            $table->foreignId('insumo_secundario_id')
                  ->nullable()
                  ->after('insumo_origen_id')
                  ->constrained('productos')
                  ->nullOnDelete();
        });

        // Drop unique constraint if exists to allow compound recipes or multiple variants
        try {
            Schema::table('recetas_transformacion', function (Blueprint $table) {
                $table->dropUnique('receta_origen_destino_unique');
            });
        } catch (\Throwable $e) {
            // Ignored if not present
        }
    }

    public function down(): void
    {
        Schema::table('recetas_transformacion', function (Blueprint $table) {
            $table->dropForeign(['insumo_secundario_id']);
            $table->dropColumn(['nombre', 'insumo_secundario_id']);
        });
    }
};
