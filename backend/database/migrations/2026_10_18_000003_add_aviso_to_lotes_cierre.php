<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes_cierre', function (Blueprint $table) {
            $table->timestamp('aviso_enviado_at')->nullable();
            $table->string('aviso_huella', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lotes_cierre', function (Blueprint $table) {
            $table->dropColumn(['aviso_enviado_at', 'aviso_huella']);
        });
    }
};
