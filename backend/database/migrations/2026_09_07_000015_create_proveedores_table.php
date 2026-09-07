<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proveedores')) {
            Schema::create('proveedores', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150)->unique();
                $table->string('contacto_nombre', 100)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('nit_o_ci', 30)->nullable();
                $table->string('direccion', 255)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            // Seed inicial de proveedores comerciales reconocidos
            DB::table('proveedores')->insert([
                [
                    'nombre' => 'Cervecería Boliviana Nacional (CBN)',
                    'contacto_nombre' => 'Preventa CBN',
                    'telefono' => '800102226',
                    'nit_o_ci' => '1020304050',
                    'direccion' => 'Av. Montes / Distribución Central',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre' => 'Embol S.A. (Coca-Cola / Bebidas)',
                    'contacto_nombre' => 'Preventa Embol',
                    'telefono' => '800103344',
                    'nit_o_ci' => '1028374021',
                    'direccion' => 'Parque Industrial',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre' => 'Licorería Punto Frío Central',
                    'contacto_nombre' => 'Administración Central',
                    'telefono' => '77712345',
                    'nit_o_ci' => '1039485022',
                    'direccion' => 'Casa Matriz Punto Frío',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre' => 'Distribuidora San Juan',
                    'contacto_nombre' => 'Juan Pérez',
                    'telefono' => '71234567',
                    'nit_o_ci' => '4938201019',
                    'direccion' => 'Calle Comercio #120',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'nombre' => 'Bodegas y Viñedos Kohlberg',
                    'contacto_nombre' => 'Preventa Vinos',
                    'telefono' => '76543210',
                    'nit_o_ci' => '1029384756',
                    'direccion' => 'Distribución Tarija / Central',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // Añadir columna opcional proveedor_id a compras si no existe
        if (Schema::hasTable('compras') && !Schema::hasColumn('compras', 'proveedor_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->foreignId('proveedor_id')->nullable()->after('usuario_id')->constrained('proveedores')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'proveedor_id')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropConstrainedForeignId('proveedor_id');
            });
        }
        Schema::dropIfExists('proveedores');
    }
};
