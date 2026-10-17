<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Huella SHA-256 del archivo recibido: la misma foto reenviada a otro grupo no debe crear otro cierre
 * (17-10: la planilla de Belén entró también por el grupo de Corona y salió un informe de "Corona").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_mensajes_inbound', function (Blueprint $table) {
            $table->string('hash_media', 64)->nullable()->after('media_path');
            $table->index('hash_media', 'wa_inbound_hash_media_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_mensajes_inbound', function (Blueprint $table) {
            $table->dropIndex('wa_inbound_hash_media_idx');
            $table->dropColumn('hash_media');
        });
    }
};
