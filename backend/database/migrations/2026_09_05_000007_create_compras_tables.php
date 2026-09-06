<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compras')) {
            Schema::create('compras', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales');
                $table->foreignId('usuario_id')->constrained('usuarios');
                $table->string('proveedor', 150);
                $table->string('numero_nota_factura', 50)->nullable();
                $table->string('foto_comprobante', 255);
                $table->decimal('total_costo_estimado', 10, 2)->default(0.00);
                $table->text('observaciones')->nullable();
                $table->dateTime('fecha_compra');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('compras_detalles')) {
            Schema::create('compras_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos');
                $table->decimal('cantidad', 8, 2);
                $table->decimal('costo_unitario', 10, 2)->default(0.00);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_detalles');
        Schema::dropIfExists('compras');
    }
};
