<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nombres con que cada casa aparece en sus documentos (encabezado del POS, sobre, planilla).
 * Todos los POS dicen "XXX CASA22" ("CORONA CASA22", "HAMACAS CASA22", "VIP ALEMANA CASA22"), así que
 * "casa22" no identifica a ninguna casa: se identifica por el nombre específico. Editable por el administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('alias_documentos', 255)->nullable()->after('codigo');
        });

        // Valores iniciales confirmados con el dueño (2026-10-17); los duplicados (CORON, MADAN, P1) quedan sin alias
        $alias = [
            'C22' => 'belen',
            'CR' => 'corona',
            'C22VIP' => 'vip,alemana',
            'MD' => 'madam,madan,madames',
            'CASA4' => 'hamacas,casa nueva,cuarta casa',
        ];
        foreach ($alias as $codigo => $valor) {
            DB::table('sucursales')->where('codigo', $codigo)->update(['alias_documentos' => $valor]);
        }
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('alias_documentos');
        });
    }
};
