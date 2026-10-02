<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpieza previa en caso de ejecución parcial previa
        Schema::dropIfExists('registros_traslados_taxis');
        Schema::dropIfExists('tarifas_rutas_taxis');

        // 1. Tarifas paramétricas de referencia entre sucursales
        Schema::create('tarifas_rutas_taxis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origen_sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('destino_sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->decimal('tarifa_estandar_bs', 8, 2);
            $table->decimal('tarifa_maxima_tolerada_bs', 8, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['origen_sucursal_id', 'destino_sucursal_id'], 'uq_tarifas_origen_destino');
        });

        // 2. Registros de traslados, rotación de chicas y taxis auditados con IA
        Schema::create('registros_traslados_taxis', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora');
            $table->foreignId('origen_sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('destino_sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('origen_texto', 150);
            $table->string('destino_texto', 150);
            $table->string('personal_trasladado', 255)->nullable();
            $table->integer('cantidad_pasajeros')->default(1);
            $table->decimal('monto_cobrado_bs', 8, 2);
            $table->decimal('tarifa_referencia_bs', 8, 2)->nullable();
            $table->decimal('sobreprecio_detectado_bs', 8, 2)->default(0.00);
            $table->boolean('es_duplicado_horario')->default(false);
            $table->text('mensaje_original_whatsapp')->nullable();
            $table->enum('estado_auditoria', [
                'conforme',
                'sobreprecio_detectado',
                'carrera_duplicada',
                'observado',
            ])->default('conforme');
            $table->foreignId('aprobado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['fecha_hora', 'estado_auditoria'], 'idx_traslados_fecha_estado');
        });

        // 3. Poblar tarifas base entre sucursales si existen (T220)
        $this->seedTarifasBase();
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_traslados_taxis');
        Schema::dropIfExists('tarifas_rutas_taxis');
    }

    private function seedTarifasBase(): void
    {
        // Buscar IDs de sucursales si ya existen
        $casas = DB::table('sucursales')->pluck('id', 'nombre')->toArray();

        $c22 = $casas['Casa22'] ?? 1;
        $coron = $casas['Casa Coron'] ?? 2;
        $madan = $casas['Madan'] ?? 3;

        $rutas = [
            ['origen' => $c22, 'destino' => $coron, 'estandar' => 15.00, 'max' => 20.00],
            ['origen' => $coron, 'destino' => $c22, 'estandar' => 15.00, 'max' => 20.00],
            ['origen' => $c22, 'destino' => $madan, 'estandar' => 15.00, 'max' => 20.00],
            ['origen' => $madan, 'destino' => $c22, 'estandar' => 15.00, 'max' => 20.00],
            ['origen' => $coron, 'destino' => $madan, 'estandar' => 20.00, 'max' => 25.00],
            ['origen' => $madan, 'destino' => $coron, 'estandar' => 20.00, 'max' => 25.00],
        ];

        foreach ($rutas as $r) {
            DB::table('tarifas_rutas_taxis')->updateOrInsert(
                [
                    'origen_sucursal_id' => $r['origen'],
                    'destino_sucursal_id' => $r['destino'],
                ],
                [
                    'tarifa_estandar_bs' => $r['estandar'],
                    'tarifa_maxima_tolerada_bs' => $r['max'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
