<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ampliar ENUM de roles en la tabla usuarios para incluir 'cajera'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('barman', 'garzon', 'cajera', 'admin') NOT NULL DEFAULT 'barman'");
        }

        // 2. Agregar campos de suplencia y auditoría de custodios en tabla turnos
        Schema::table('turnos', function (Blueprint $table) {
            if (!Schema::hasColumn('turnos', 'realizado_por_usuario_id')) {
                $table->foreignId('realizado_por_usuario_id')->nullable()->after('barman_id')->constrained('usuarios')->nullOnDelete();
            }
            if (!Schema::hasColumn('turnos', 'cerrado_por_usuario_id')) {
                $table->foreignId('cerrado_por_usuario_id')->nullable()->after('realizado_por_usuario_id')->constrained('usuarios')->nullOnDelete();
            }
            if (!Schema::hasColumn('turnos', 'es_suplencia')) {
                $table->boolean('es_suplencia')->default(false)->after('cerrado_por_usuario_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            if (Schema::hasColumn('turnos', 'realizado_por_usuario_id')) {
                $table->dropForeign(['realizado_por_usuario_id']);
                $table->dropColumn('realizado_por_usuario_id');
            }
            if (Schema::hasColumn('turnos', 'cerrado_por_usuario_id')) {
                $table->dropForeign(['cerrado_por_usuario_id']);
                $table->dropColumn('cerrado_por_usuario_id');
            }
            if (Schema::hasColumn('turnos', 'es_suplencia')) {
                $table->dropColumn('es_suplencia');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('barman', 'garzon', 'admin') NOT NULL DEFAULT 'barman'");
        }
    }
};
