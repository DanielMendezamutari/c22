<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'sueldo_base_semanal')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->decimal('sueldo_base_semanal', 10, 2)->default(0.00)->after('modalidad_cobro');
            });
        }

        if (!Schema::hasTable('traspasos_detalles')) {
            Schema::create('traspasos_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('traspaso_id')->constrained('traspasos')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos');
                $table->decimal('cantidad_despachada', 8, 2);
                $table->decimal('cantidad_recibida_conforme', 8, 2)->default(0.00);
                $table->decimal('cantidad_merma_transito', 8, 2)->default(0.00);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traspasos_detalles');

        if (Schema::hasColumn('usuarios', 'sueldo_base_semanal')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropColumn('sueldo_base_semanal');
            });
        }
    }
};
