<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('turnos') && !Schema::hasColumn('turnos', 'foto_comprobante_cobro')) {
            Schema::table('turnos', function (Blueprint $table) {
                $table->string('foto_comprobante_cobro', 255)->nullable()->after('codigo_recibo_cobro');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('turnos', 'foto_comprobante_cobro')) {
            Schema::table('turnos', function (Blueprint $table) {
                $table->dropColumn('foto_comprobante_cobro');
            });
        }
    }
};
