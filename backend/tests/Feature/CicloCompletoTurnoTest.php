<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\CorteInventario;
use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use App\Infrastructure\Persistence\Eloquent\Models\Proveedor;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaTransformacion;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CicloCompletoTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalCasa22;
    private Sucursal $sucursalMadan;
    private Usuario $barman;
    private Usuario $admin;
    private Producto $prodMoema;
    private Producto $prodCorona;
    private Producto $prodFernet;
    private RecetaTransformacion $receta;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear Sucursales
        $this->sucursalCasa22 = Sucursal::create([
            'nombre' => 'Casa22',
            'codigo' => 'C22',
            'direccion' => 'Calle 22 de Calacoto',
            'activo' => true,
        ]);

        $this->sucursalMadan = Sucursal::create([
            'nombre' => 'Madan',
            'codigo' => 'MAD',
            'direccion' => 'Av. San Martín',
            'activo' => true,
        ]);

        // 2. Crear Usuarios (Barman y Admin)
        $this->barman = Usuario::create([
            'nombre' => 'María Roxana',
            'apellido' => 'Rojas',
            'pin_hash' => Hash::make('1234'),
            'rol' => 'barman',
            'sucursal_actual_id' => $this->sucursalCasa22->id,
            'modalidad_cobro' => 'diario',
            'saldo_deudor_acumulado' => 0.00,
            'activo' => true,
        ]);

        $this->admin = Usuario::create([
            'nombre' => 'Daniel',
            'apellido' => 'Méndez',
            'pin_hash' => Hash::make('9999'),
            'rol' => 'admin',
            'sucursal_actual_id' => $this->sucursalCasa22->id,
            'modalidad_cobro' => 'semanal',
            'saldo_deudor_acumulado' => 0.00,
            'activo' => true,
        ]);

        // 3. Crear Productos
        $this->prodMoema = Producto::create([
            'nombre' => 'Moema Lata',
            'codigo_barra' => 'MOE-01',
            'tipo' => 'insumo',
            'precio_venta' => 15.00,
            'costo_unitario' => 8.00,
            'activo' => true,
        ]);

        $this->prodCorona = Producto::create([
            'nombre' => 'Corona Original',
            'codigo_barra' => 'COR-01',
            'tipo' => 'terminado',
            'precio_venta' => 25.00,
            'costo_unitario' => 12.00,
            'activo' => true,
        ]);

        $this->prodFernet = Producto::create([
            'nombre' => 'Fernet 750',
            'codigo_barra' => 'FER-750',
            'tipo' => 'ambos',
            'precio_venta' => 120.00,
            'costo_unitario' => 60.00,
            'activo' => true,
        ]);

        // 4. Receta de Transformación (Moema -> Corona, comisión 1 Bs)
        $this->receta = RecetaTransformacion::create([
            'nombre' => 'Moema Lata a Corona',
            'insumo_origen_id' => $this->prodMoema->id,
            'producto_destino_id' => $this->prodCorona->id,
            'tarifa_comision_unidad' => 1.00,
            'ratio_referencia_esperado' => 1.000,
            'umbral_desviacion_alerta' => 15.00,
            'activo' => true,
        ]);

        // 5. Proveedor
        $this->proveedor = Proveedor::create([
            'nombre' => 'Cervecería Boliviana Nacional',
            'contacto_nombre' => 'Juan Pérez',
            'telefono' => '77712345',
            'nit_o_ci' => '1020304050',
            'direccion' => 'Av. Montes',
            'activo' => true,
        ]);
    }

    public function test_ciclo_completo_de_vida_del_turno_y_reglas_constitucionales(): void
    {
        // -------------------------------------------------------------
        // PASO 1: Login de Barman con PIN de 4 dígitos
        // -------------------------------------------------------------
        $resLogin = $this->postJson('/api/v1/auth/login-pin', [
            'pin' => '1234',
            'sucursal_id' => $this->sucursalCasa22->id,
        ]);

        $resLogin->assertStatus(200);
        $resLogin->assertJsonStructure(['success', 'data' => ['token', 'usuario']]);
        $token = $resLogin->json('data.token');
        $headers = ['Authorization' => "Bearer $token"];

        // -------------------------------------------------------------
        // PASO 2: Apertura de Turno con Conteo Físico Real (US23 / FR-044)
        // -------------------------------------------------------------
        $corteInicial = [
            ['producto_id' => $this->prodMoema->id, 'cantidad' => 24.00],
            ['producto_id' => $this->prodCorona->id, 'cantidad' => 10.00],
            ['producto_id' => $this->prodFernet->id, 'cantidad' => 2.50],
        ];

        $resAbrir = $this->withHeaders($headers)->postJson('/api/v1/turnos/abrir', [
            'sucursal_id' => $this->sucursalCasa22->id,
            'barman_id' => $this->barman->id,
            'tipo_turno' => 'dia',
            'corte_inicial' => $corteInicial,
        ]);

        $resAbrir->assertStatus(201);
        $turnoId = $resAbrir->json('data.turno_id');
        $this->assertNotNull($turnoId);

        // Validar que el corte se asentó en base de datos
        $this->assertDatabaseHas('cortes_inventario', [
            'turno_id' => $turnoId,
            'producto_id' => $this->prodMoema->id,
            'cantidad' => 24.00,
        ]);

        // -------------------------------------------------------------
        // PASO 3: Verificar que el endpoint de Corte Inicial retorna cantidades reales
        // -------------------------------------------------------------
        $resCorteInicial = $this->withHeaders($headers)->getJson("/api/v1/turnos/{$turnoId}/corte-inicial");
        $resCorteInicial->assertStatus(200);
        $items = collect($resCorteInicial->json('data.items'));

        $itemMoema = $items->firstWhere('producto_id', $this->prodMoema->id);
        $this->assertNotNull($itemMoema);
        $this->assertEquals(24.00, (float) $itemMoema['cantidad_inicial']);
        $this->assertEquals(24.00, (float) $itemMoema['total_disponible']);

        $itemFernet = $items->firstWhere('producto_id', $this->prodFernet->id);
        $this->assertEquals(2.50, (float) $itemFernet['cantidad_inicial']);

        // -------------------------------------------------------------
        // -------------------------------------------------------------
        // PASO 4: Recepción de Compra con Proveedor (+12 Moema)
        // -------------------------------------------------------------
        $resCompra = $this->withHeaders($headers)->postJson('/api/v1/inventario/compras', [
            'sucursal_id' => $this->sucursalCasa22->id,
            'proveedor' => $this->proveedor->nombre,
            'proveedor_id' => $this->proveedor->id,
            'numero_nota_factura' => 'FAC-9988',
            'foto_comprobante' => 'data:image/jpeg;base64,' . base64_encode('fake-image-content'),
            'items' => [
                ['producto_id' => $this->prodMoema->id, 'cantidad' => 12.0, 'costo_unitario' => 8.0],
            ],
        ]);
        $resCompra->assertStatus(201);

        // Verificar que el corte inicial ahora refleje los ingresos (+12.00)
        $resCorteConIngreso = $this->withHeaders($headers)->getJson("/api/v1/turnos/{$turnoId}/corte-inicial");
        $itemsIngreso = collect($resCorteConIngreso->json('data.items'));
        $itemMoemaActualizado = $itemsIngreso->firstWhere('producto_id', $this->prodMoema->id);
        $this->assertEquals(12.00, (float) $itemMoemaActualizado['ingresos']);
        $this->assertEquals(36.00, (float) $itemMoemaActualizado['total_disponible']); // 24 + 12 = 36

        // -------------------------------------------------------------
        // PASO 5: Transformación (Relleno) con Merma y Comisión Neta
        // Consumir 12 Moema -> Producir 11 Corona + 1 Botella rota
        // -------------------------------------------------------------
        $resTransf = $this->withHeaders($headers)->postJson('/api/v1/transformaciones/relleno', [
            'uuid_local' => (string) \Illuminate\Support\Str::uuid(),
            'turno_id' => $turnoId,
            'receta_id' => $this->receta->id,
            'insumo_origen_id' => $this->prodMoema->id,
            'cantidad_insumo' => 12.0,
            'cantidad_producida' => 11,
            'cantidad_roturas' => 1,
            'observaciones' => 'Relleno de botellas con 1 rotura',
        ]);
        $resTransf->assertStatus(201);

        // Validación Constitucional: Comisión = (11 producidas - 1 rota) x 1 Bs = 10 Bs netos
        $comision = (float) $resTransf->json('data.comision_generada_bs');
        $this->assertEquals(10.00, $comision, 'Principio II: Comisión debe liquidarse exclusivamente sobre unidades netas transformadas');
        $this->assertEquals(10, $resTransf->json('data.unidades_netas'));

        // -------------------------------------------------------------
        // PASO 6: Traspaso a otra sucursal (-2 Coronas a Madan)
        // -------------------------------------------------------------
        $resTraspaso = $this->withHeaders($headers)->postJson('/api/v1/traspasos/enviar', [
            'sucursal_origen_id' => $this->sucursalCasa22->id,
            'sucursal_destino_id' => $this->sucursalMadan->id,
            'producto_id' => $this->prodCorona->id,
            'cantidad' => 2.0,
            'observaciones' => 'Traspaso para abastecer Madan',
        ]);
        $resTraspaso->assertStatus(201);

        // -------------------------------------------------------------
        // PASO 7: Corte de Cierre de Turno e Inmutabilidad
        // Stock inicial Corona = 10, Producidas netas = +11, Traspaso = -2 => Total Disponible = 19
        // Si el barman cuenta 19 en cierre => Venta = 0
        // Si el barman cuenta 15 en cierre => Consumo físico = 4
        // -------------------------------------------------------------
        $corteFinal = [
            ['producto_id' => $this->prodMoema->id, 'cantidad' => 24.00], // 24 + 12 - 12 = 24
            ['producto_id' => $this->prodCorona->id, 'cantidad' => 15.00], // Físico restante 15
            ['producto_id' => $this->prodFernet->id, 'cantidad' => 2.50],
        ];

        $resCerrar = $this->withHeaders($headers)->postJson("/api/v1/turnos/{$turnoId}/cerrar", [
            'corte_final' => $corteFinal,
        ]);
        $resCerrar->assertStatus(200);

        // Verificar que el turno pasó a estado 'cerrado'
        $turno = Turno::find($turnoId);
        $this->assertEquals('cerrado', $turno->estado);

        // Verificar inmutabilidad: Intentar cerrar de nuevo debe ser rechazado
        $resCerrarDuplicado = $this->withHeaders($headers)->postJson("/api/v1/turnos/{$turnoId}/cerrar", [
            'corte_final' => $corteFinal,
        ]);
        $resCerrarDuplicado->assertStatus(400);

        // -------------------------------------------------------------
        // PASO 8: Conciliación de Ticket Z (Auditoría Administrativa)
        // Consumo físico = 19 disponible - 1 rotura (baja) - 15 cierre = 3 Coronas
        // Ventas registradas en caja = 3 Coronas
        // Diferencia = 0 (Cuadrado / Verde)
        // -------------------------------------------------------------
        $loginAdmin = $this->postJson('/api/v1/auth/login-pin', ['pin' => '9999']);
        $tokenAdmin = $loginAdmin->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer $tokenAdmin"];

        $resConciliar = $this->withHeaders($adminHeaders)->postJson('/api/v1/auditoria/calcular', [
            'turno_id' => $turnoId,
            'producto_id' => $this->prodCorona->id,
            'ventas_ticket_z' => [
                'productos_individuales' => [
                    ['producto_id' => $this->prodCorona->id, 'cantidad_vendida' => 3.0],
                ],
            ],
        ]);

        $resConciliar->assertStatus(200);
        $auditoria = $resConciliar->json('data');
        $this->assertNotNull($auditoria);
        $this->assertEquals(0.00, (float) $auditoria['diferencia']);
        $this->assertEquals('cuadrado', $auditoria['resultado']);
        $this->assertEquals('verde', $auditoria['alerta_color']);
    }
}
