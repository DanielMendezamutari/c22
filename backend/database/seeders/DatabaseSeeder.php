<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Sucursal principal base
        $casa22 = Sucursal::firstOrCreate(['codigo' => 'C22'], [
            'nombre' => 'Casa22',
            'direccion' => 'Calle 22 de Calacoto #100',
            'activo' => true,
        ]);

        // 2. Administrador General del Sistema
        Usuario::firstOrCreate(['nombre' => 'Daniel', 'apellido' => 'Méndez Amutari'], [
            'pin_hash' => Hash::make('9999'),
            'rol' => 'admin',
            'sucursal_actual_id' => $casa22->id,
            'modalidad_cobro' => 'semanal',
            'saldo_deudor_acumulado' => 0.00,
            'activo' => true,
        ]);

        // 3. Catálogo de productos: 0 (Limpio para creación manual)
        // 4. Recetas de transformación: 0 (Limpio para creación manual)
        // 5. Recetas de combos: 0 (Limpio para creación manual)
        // 6. Turnos operativos: 0 (Limpio para creación manual)
    }
}

