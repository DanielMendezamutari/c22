<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La migración 2026_10_18_000001 ya estaba aplicada en producción cuando se agregó la evidencia
 * 'encabezado_remitente'. Como texto, nuevas evidencias no requieren otra migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_cierre', function (Blueprint $table) {
            $table->string('evidencia_casa', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás a enum: se perderían valores ya guardados.
    }
};
