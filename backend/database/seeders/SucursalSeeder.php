<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    public function run(): void
    {
        $casas = [
            [
                'codigo' => 'C22',
                'nombre' => 'Casa Belén (C22)',
                'direccion' => 'Sede Central Belén / C22',
                'activo' => true,
            ],
            [
                'codigo' => 'CORON',
                'nombre' => 'Casa Corona',
                'direccion' => 'Sede Casa Corona',
                'activo' => true,
            ],
            [
                'codigo' => 'MADAN',
                'nombre' => 'Madan',
                'direccion' => 'Sede Madan',
                'activo' => true,
            ],
            [
                'codigo' => 'CASA4',
                'nombre' => 'Cuarta Casa',
                'direccion' => 'Sede 4',
                'activo' => true,
            ],
        ];

        foreach ($casas as $casa) {
            Sucursal::firstOrCreate(['codigo' => $casa['codigo']], $casa);
        }
    }
}
