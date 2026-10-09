<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use App\Infrastructure\Persistence\Eloquent\Models\PosTransaccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConciliacionTrianguladaResilienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_conciliacion_muestra_pendiente_planilla_sin_falsa_alarma_de_robo(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Casa22 Central',
            'codigo' => 'C22',
            'activo' => true,
        ]);

        $usuario = Usuario::create([
            'nombre' => 'Barman',
            'apellido' => 'Test',
            'rol' => 'barman',
            'pin_hash' => bcrypt('1234'),
            'sucursal_actual_id' => $sucursal->id,
            'activo' => true,
        ]);

        $turno = Turno::create([
            'sucursal_id' => $sucursal->id,
            'barman_id' => $usuario->id,
            'tipo_turno' => 'noche',
            'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => now(),
            'estado' => 'cerrado',
        ]);

        $producto = \App\Infrastructure\Persistence\Eloquent\Models\Producto::create([
            'nombre' => 'Botella Fernet Branca',
            'tipo' => 'terminado',
        ]);

        // Registrar cortes de 10 botellas consumidas en barra para que la barra cuadre exactamente con el POS
        \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::create([
            'turno_id' => $turno->id,
            'producto_id' => $producto->id,
            'tipo_corte' => 'apertura',
            'cantidad' => 20,
        ]);
        \App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::create([
            'turno_id' => $turno->id,
            'producto_id' => $producto->id,
            'tipo_corte' => 'cierre',
            'cantidad' => 10,
        ]);

        // Simular 8,500 Bs en ventas registradas por POS SQL Server
        PosTransaccion::create([
            'sucursal_id' => $sucursal->id,
            'turno_id' => $turno->id,
            'pos_transaccion_id' => 'TX-9901',
            'pos_detalle_id' => 'DET-9901',
            'pos_cuenta_id' => '101',
            'pos_producto_id' => 'P-01',
            'pos_nombre_producto' => 'Botella Fernet Branca',
            'cantidad' => 10,
            'precio_unitario' => 850.00,
            'subtotal' => 8500.00,
            'metodo_pago' => 'efectivo',
            'fecha_hora' => now()->subHours(2),
            'estado_mapeo' => 'mapeado',
        ]);

        // Consultar la conciliación triangulada cuando aún NO se envió la planilla
        $response = $this->getJson("/api/v1/auditoria/conciliacion-triangulada/{$turno->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'vertice_1_pos' => [
                        'ventas_brutas_bs' => 8500.00,
                        'efectivo_bs' => 8500.00,
                    ],
                    'vertice_2_planilla' => [
                        'estado' => '[PENDIENTE_PLANILLA]',
                        'tiene_foto_planilla' => false,
                    ],
                    'auditoria_financiera' => [
                        'estado' => 'pendiente_planilla',
                        'diferencia_efectivo_bs' => null,
                        'imputado_a' => null,
                    ],
                    'estado_semaforo' => 'ambar_observado',
                ],
            ]);

        // Subir planilla manual de contingencia
        $subidaResponse = $this->postJson('/api/v1/auditoria/subir-planilla-manual', [
            'turno_id' => $turno->id,
            'total_ventas_declaradas_bs' => 8500.00,
            'total_gastos_declarados_bs' => 200.00,
            'monto_sobre_efectivo_bs' => 8300.00,
            'cajero_nombre' => 'Mariela Encargada',
        ]);

        $subidaResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Al consultar nuevamente, Vértice 2 pasa a [RECIBIDA]
        $segundaConsulta = $this->getJson("/api/v1/auditoria/conciliacion-triangulada/{$turno->id}");
        $segundaConsulta->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'vertice_2_planilla' => [
                        'estado' => '[RECIBIDA]',
                        'efectivo_declarado_bs' => 8500.00,
                    ],
                    'auditoria_financiera' => [
                        'diferencia_efectivo_bs' => 0.00,
                        'estado' => 'cuadrado',
                    ],
                ],
            ]);
    }
}
