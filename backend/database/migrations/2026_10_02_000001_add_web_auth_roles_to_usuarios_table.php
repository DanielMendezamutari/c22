<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar ENUM de roles para incluir roles web de gestión y auditoría
        DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('barman', 'garzon', 'cajera', 'admin', 'super_admin', 'dueno', 'contadora', 'auxiliar_contable') NOT NULL DEFAULT 'barman'");

        // 2. Agregar campos para autenticación web (email y password_hash)
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'email')) {
                $table->string('email', 150)->nullable()->unique()->after('apellido');
            }
            if (!Schema::hasColumn('usuarios', 'password_hash')) {
                $table->string('password_hash', 255)->nullable()->after('pin_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios', 'password_hash')) {
                $table->dropColumn('password_hash');
            }
            if (Schema::hasColumn('usuarios', 'email')) {
                $table->dropColumn('email');
            }
        });

        DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('barman', 'garzon', 'cajera', 'admin') NOT NULL DEFAULT 'barman'");
    }
};
