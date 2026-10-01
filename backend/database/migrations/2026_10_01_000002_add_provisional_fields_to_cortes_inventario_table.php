<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes_inventario', function (Blueprint $table) {
            // Permitir null en producto_id para productos no catalogados provisionales
            if (Schema::hasColumn('cortes_inventario', 'producto_id')) {
                $table->unsignedBigInteger('producto_id')->nullable()->change();
            }

            if (!Schema::hasColumn('cortes_inventario', 'es_provisional')) {
                $table->boolean('es_provisional')->default(false)->after('cantidad');
            }

            if (!Schema::hasColumn('cortes_inventario', 'nombre_provisional')) {
                $table->string('nombre_provisional', 150)->nullable()->after('es_provisional');
            }

            if (!Schema::hasColumn('cortes_inventario', 'es_licor')) {
                $table->boolean('es_licor')->default(false)->after('nombre_provisional');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cortes_inventario', function (Blueprint $table) {
            if (Schema::hasColumn('cortes_inventario', 'es_licor')) {
                $table->dropColumn('es_licor');
            }
            if (Schema::hasColumn('cortes_inventario', 'nombre_provisional')) {
                $table->dropColumn('nombre_provisional');
            }
            if (Schema::hasColumn('cortes_inventario', 'es_provisional')) {
                $table->dropColumn('es_provisional');
            }
        });
    }
};
