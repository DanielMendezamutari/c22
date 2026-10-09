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
        Schema::create('sucursal_whatsapp_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('remote_jid', 100)->unique();
            $table->string('nombre_grupo', 150);
            $table->enum('tipo_auditoria', [
                'cierre_recaudacion',
                'gastos_caja_chica',
                'taxis_rotacion',
                'general'
            ])->default('cierre_recaudacion');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['sucursal_id', 'tipo_auditoria']);
        });

        // Opcional: Columna whatsapp_group_jid en sucursales para fallback rápido
        if (!Schema::hasColumn('sucursales', 'whatsapp_group_jid')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->string('whatsapp_group_jid', 100)->nullable()->after('activo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursal_whatsapp_grupos');

        if (Schema::hasColumn('sucursales', 'whatsapp_group_jid')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropColumn('whatsapp_group_jid');
            });
        }
    }
};
