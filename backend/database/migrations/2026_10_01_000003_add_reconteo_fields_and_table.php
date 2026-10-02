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
        // 1. Agregar columnas para gestión de reconteo en tabla turnos
        Schema::table('turnos', function (Blueprint $table) {
            if (!Schema::hasColumn('turnos', 'permite_reconteo')) {
                $table->boolean('permite_reconteo')->default(false)->after('foto_comprobante_cobro');
            }
            if (!Schema::hasColumn('turnos', 'reconteo_tipo')) {
                $table->enum('reconteo_tipo', ['apertura', 'cierre'])->nullable()->after('permite_reconteo');
            }
            if (!Schema::hasColumn('turnos', 'reconteo_autorizado_por_id')) {
                $table->foreignId('reconteo_autorizado_por_id')->nullable()->after('reconteo_tipo')->constrained('usuarios')->nullOnDelete();
            }
            if (!Schema::hasColumn('turnos', 'reconteo_autorizado_at')) {
                $table->dateTime('reconteo_autorizado_at')->nullable()->after('reconteo_autorizado_por_id');
            }
            if (!Schema::hasColumn('turnos', 'reconteo_motivo')) {
                $table->string('reconteo_motivo', 255)->nullable()->after('reconteo_autorizado_at');
            }
        });

        // 2. Crear tabla de auditoría para trazabilidad inmutable de reconteos
        if (!Schema::hasTable('auditorias_reconteo')) {
            Schema::create('auditorias_reconteo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios');
                $table->foreignId('admin_id')->constrained('usuarios');
                $table->enum('tipo_corte', ['apertura', 'cierre']);
                $table->string('motivo', 255);
                $table->json('detalles_json'); // array de [{producto_id, nombre_producto, valor_anterior, valor_nuevo, diferencia}]
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias_reconteo');

        Schema::table('turnos', function (Blueprint $table) {
            if (Schema::hasColumn('turnos', 'reconteo_autorizado_por_id')) {
                $table->dropForeign(['reconteo_autorizado_por_id']);
                $table->dropColumn('reconteo_autorizado_por_id');
            }
            if (Schema::hasColumn('turnos', 'permite_reconteo')) {
                $table->dropColumn('permite_reconteo');
            }
            if (Schema::hasColumn('turnos', 'reconteo_tipo')) {
                $table->dropColumn('reconteo_tipo');
            }
            if (Schema::hasColumn('turnos', 'reconteo_autorizado_at')) {
                $table->dropColumn('reconteo_autorizado_at');
            }
            if (Schema::hasColumn('turnos', 'reconteo_motivo')) {
                $table->dropColumn('reconteo_motivo');
            }
        });
    }
};
