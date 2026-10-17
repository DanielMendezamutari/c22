<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El enum rechazaba 'aviso_lote_cierre' en MySQL (SQLite no valida enums). Como texto, nuevos tipos no
 * requieren otra migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes_outbound', function (Blueprint $table) {
            $table->string('tipo_mensaje', 40)->default('informe_cierre_ejecutivo')->change();
        });
    }

    public function down(): void
    {
        // Sin vuelta atrás a enum: se perderían valores ya guardados.
    }
};
