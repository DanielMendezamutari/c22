<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El enum original de clasificacion_ia no admitía 'ticket_pos' (cada ticket POS
 * terminaba en estado 'error' con MySQL estricto) ni el nuevo 'cierre_barra';
 * estado no admitía 'requiere_revision'. Se pasan a string y se validan en código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes_inbound', function (Blueprint $table) {
            $table->string('clasificacion_ia', 30)->default('desconocido')->change();
            $table->string('estado', 30)->default('pendiente_proceso')->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes_inbound', function (Blueprint $table) {
            $table->enum('clasificacion_ia', ['planilla_caja', 'voucher_deposito', 'recibo_gasto', 'otro', 'desconocido'])
                ->default('desconocido')->change();
            $table->enum('estado', ['pendiente_proceso', 'procesado', 'requiere_confirmacion', 'error'])
                ->default('pendiente_proceso')->change();
        });
    }
};
