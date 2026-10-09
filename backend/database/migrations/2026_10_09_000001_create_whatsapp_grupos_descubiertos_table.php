<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_grupos_descubiertos', function (Blueprint $table) {
            $table->id();
            $table->string('remote_jid', 100)->unique();
            $table->string('nombre_grupo', 150);
            $table->unsignedInteger('participantes_count')->default(0);
            $table->timestamp('ultima_deteccion_at')->useCurrent();
            $table->timestamps();

            $table->index('ultima_deteccion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_grupos_descubiertos');
    }
};
