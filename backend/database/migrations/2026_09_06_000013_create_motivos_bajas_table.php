<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_bajas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Insert initial standard reasons
        DB::table('motivos_bajas')->insert([
            ['descripcion' => 'Rotura Accidental en Barra', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Botella Quebrada en Descorche', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Corona Rellenada con Defecto / Sin Gas', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Vencimiento de Producto', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Derrame Accidental en Servicio', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Pérdida o Merma en Transporte', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'Otro Motivo Justificado', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_bajas');
    }
};
