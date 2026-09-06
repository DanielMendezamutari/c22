<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY COLUMN uuid_local VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimientos_inventario MODIFY COLUMN uuid_local CHAR(36) NOT NULL");
    }
};
