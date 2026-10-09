<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use App\Infrastructure\Persistence\Eloquent\Models\JornalGarzon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiquidacionJornalGarzonTest extends TestCase
{
    use RefreshDatabase;

    public function test_liquidacion_jornal_garzon_descuenta_botellas_faltantes_al_costo(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Casa22 Central',
            'codigo' => 'C22',
            'activo' => true,
        ]);

        $garzon = Usuario::create([
            'nombre' => 'Carlos',
            'apellido' => 'Garzon',
            'rol' => 'garzon',
            'pin_hash' => bcrypt('5678'),
            'sucursal_actual_id' => $sucursal->id,
            'activo' => true,
        ]);

        $turno = Turno::create([
            'sucursal_id' => $sucursal->id,
            'barman_id' => $garzon->id,
            'tipo_turno' => 'dia',
            'fecha_apertura' => now()->subHours(10),
            'fecha_cierre' => now(),
            'estado' => 'cerrado',
        ]);

        // Simular Scenario 18: Jornal Base 120 Bs, 1 botella faltante (15 Bs costo) -> Total neto 105 Bs
        $payload = [
            'usuario_id' => $garzon->id,
            'jornal_base_bs' => 120.00,
            'faltante_botellas_unidades' => 1.00,
            'costo_unitario_bs' => 15.00,
            'descuento_faltante_bs' => 15.00,
            'total_neto_pagado_bs' => 105.00,
            'foto_comprobante_url' => 'storage/comprobantes/jornales/recibo_15.jpg',
        ];

        $response = $this->postJson("/api/v1/turnos/{$turno->id}/liquidar-garzon", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'turno_id' => $turno->id,
                    'usuario_id' => $garzon->id,
                    'sucursal_id' => $sucursal->id,
                    'jornal_base_bs' => '120.00',
                    'faltante_botellas_unidades' => '1.00',
                    'descuento_faltante_bs' => '15.00',
                    'total_neto_pagado_bs' => '105.00',
                    'estado' => 'pagado_en_caja',
                ],
            ]);

        $this->assertDatabaseHas('jornales_garzones', [
            'turno_id' => $turno->id,
            'usuario_id' => $garzon->id,
            'total_neto_pagado_bs' => 105.00,
        ]);
    }

    public function test_liquidacion_jornal_evita_saldo_negativo_si_faltante_supera_jornal(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Madan',
            'codigo' => 'MADAN',
            'activo' => true,
        ]);

        $garzon = Usuario::create([
            'nombre' => 'Roberto',
            'apellido' => 'Mendoza',
            'rol' => 'garzon',
            'pin_hash' => bcrypt('1122'),
            'sucursal_actual_id' => $sucursal->id,
            'activo' => true,
        ]);

        $turno = Turno::create([
            'sucursal_id' => $sucursal->id,
            'barman_id' => $garzon->id,
            'tipo_turno' => 'dia',
            'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => now(),
            'estado' => 'cerrado',
        ]);

        // Faltante de 10 botellas (150 Bs) con jornal de 120 Bs -> Clamped a 0.00 Bs
        $payload = [
            'usuario_id' => $garzon->id,
            'jornal_base_bs' => 120.00,
            'faltante_botellas_unidades' => 10.00,
            'costo_unitario_bs' => 15.00,
            'descuento_faltante_bs' => 150.00,
        ];

        $response = $this->postJson("/api/v1/turnos/{$turno->id}/liquidar-garzon", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_neto_pagado_bs' => '0.00',
                ],
            ]);
    }
}
