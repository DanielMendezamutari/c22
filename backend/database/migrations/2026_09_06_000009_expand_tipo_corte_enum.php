<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cortes_inventario MODIFY COLUMN tipo_corte ENUM('inicial', 'final', 'apertura', 'cierre') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cortes_inventario MODIFY COLUMN tipo_corte ENUM('inicial', 'final') NOT NULL");
        }
    }
};
