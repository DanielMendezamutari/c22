<?php

namespace Tests\Feature;

use App\Application\UseCases\Auditoria\AuditoriaCruceCanalesUseCase;
use App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaCruceCanales;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Services\GeminiVisionAuditorService;
use App\Services\WhatsAppOutboundDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAuditoriaCruceYDespachoRecaudoTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalBelen;
    private SucursalWhatsAppGrupo $grupoLocal;
    private SucursalWhatsAppGrupo $grupoGlobal;
    private SucursalWhatsAppGrupo $grupoRecaudo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursalBelen = Sucursal::create([
            'nombre' => 'C22 - Casa Belén',
            'codigo' => 'C22',
            'activo' => true,
        ]);

        $this->grupoLocal = SucursalWhatsAppGrupo::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'remote_jid' => '120363001@g.us',
            'nombre_grupo' => 'C22 Belén Cierres',
            'tipo_auditoria' => 'cierre_recaudacion',
            'es_global' => false,
            'activo' => true,
        ]);

        $this->grupoGlobal = SucursalWhatsAppGrupo::create([
            'sucursal_id' => null,
            'remote_jid' => '120363999@g.us',
            'nombre_grupo' => '[General] Cierres y Recaudaciones',
            'tipo_auditoria' => 'general',
            'es_global' => true,
            'activo' => true,
        ]);

        $this->grupoRecaudo = SucursalWhatsAppGrupo::create([
            'sucursal_id' => null,
            'remote_jid' => '120363777@g.us',
            'nombre_grupo' => 'Control de Recaudo (Consolidado 4 Casas)',
            'tipo_auditoria' => 'control_recaudo_global',
            'es_global' => true,
            'activo' => true,
        ]);
    }

    public function test_escenario_21_deduplicacion_inter_canales_conforme(): void
    {
        $hashImagen = 'sha256_mock_hash_imagen_belen_8500';

        // 1. Mensaje en canal local
        $msgLocal = WhatsAppMensajeInbound::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'remote_jid' => $this->grupoLocal->remote_jid,
            'sender_phone' => '59170011223',
            'sender_name' => 'Mariela',
            'tipo_mensaje' => 'imagen',
            'raw_text' => 'Planilla Belen 8500 Bs',
            'estado' => 'procesado',
        ]);

        // 2. Mensaje en canal global
        $msgGlobal = WhatsAppMensajeInbound::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'remote_jid' => $this->grupoGlobal->remote_jid,
            'sender_phone' => '59170011223',
            'sender_name' => 'Mariela',
            'tipo_mensaje' => 'imagen',
            'raw_text' => 'Copia para general planilla Belen',
            'estado' => 'procesado',
        ]);

        $useCase = new AuditoriaCruceCanalesUseCase();

        // Procesar canal local
        $cruce1 = $useCase->ejecutar($msgLocal->id, $this->sucursalBelen->id, 'planilla_cierre', 8500.00, $hashImagen);
        $this->assertEquals('solo_canal_local', $cruce1->estado_cruce);

        // Procesar canal global con la misma imagen
        $cruce2 = $useCase->ejecutar($msgGlobal->id, $this->sucursalBelen->id, 'planilla_cierre', 8500.00, $hashImagen);

        $this->assertEquals($cruce1->id, $cruce2->id);
        $this->assertEquals('conforme_doble_canal', $cruce2->estado_cruce);
        $this->assertEquals(0.00, (float) $cruce2->diferencia_bs);
    }

    public function test_escenario_21_deteccion_y_resolucion_discrepancia(): void
    {
        $hashImagen = 'sha256_gasto_hielo_123';

        $msgLocal = WhatsAppMensajeInbound::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'remote_jid' => $this->grupoLocal->remote_jid,
            'sender_phone' => '59170011223',
            'sender_name' => 'Mariela',
            'tipo_mensaje' => 'imagen',
            'raw_text' => 'Gasto hielo 300 Bs',
            'estado' => 'procesado',
        ]);

        $msgGlobal = WhatsAppMensajeInbound::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'remote_jid' => $this->grupoGlobal->remote_jid,
            'sender_phone' => '59170011223',
            'sender_name' => 'Mariela',
            'tipo_mensaje' => 'imagen',
            'raw_text' => 'Gasto hielo 500 Bs',
            'estado' => 'procesado',
        ]);

        $useCase = new AuditoriaCruceCanalesUseCase();
        $useCase->ejecutar($msgLocal->id, $this->sucursalBelen->id, 'gasto_caja_chica', 300.00, $hashImagen);
        $cruce = $useCase->ejecutar($msgGlobal->id, $this->sucursalBelen->id, 'gasto_caja_chica', 500.00, $hashImagen);

        $this->assertEquals('discrepancia_detectada', $cruce->estado_cruce);
        $this->assertEquals(-200.00, (float) $cruce->diferencia_bs);

        // Resolver vía API
        $response = $this->postJson('/api/v1/whatsapp/resolver-discrepancia-cruce', [
            'cruce_id' => $cruce->id,
            'monto_definitivo_auditado' => 300.00,
            'imputar_responsable' => 'cajera',
            'observaciones' => 'Se valida que el recibo real es por 300 Bs. Error de tipeo en general.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'cruce_id' => $cruce->id,
                'estado_cruce' => 'justificado',
                'monto_final' => 300.00,
            ],
        ]);

        $cruce->refresh();
        $this->assertEquals('justificado', $cruce->estado_cruce);
        $this->assertEquals(300.00, (float) $cruce->monto_definitivo_auditado);
    }

    public function test_escenario_22_conciliar_lote_cierre_modo_solo_whatsapp_con_discrepancia_qr(): void
    {
        $visionService = app(GeminiVisionAuditorService::class);

        $textoWhatsApp = "Casa Belén\nVenta total:32690\nQr:14448\nEfectivo:1510\nFanny:599";
        
        $fotos = [
            [
                'clasificacion' => 'planilla_caja',
                'datos' => [
                    'total_ventas_declaradas_bs' => 32690.00,
                    'total_gastos_declarados_bs' => 18732.00,
                    'qr_declarado_bs' => 12448.00,
                    'monto_sobre_efectivo_bs' => 1510.00,
                ],
            ],
            [
                'clasificacion' => 'ticket_pos',
                'datos' => [
                    'total_ticket_bs' => 32490.00,
                    'qr_pos_bs' => 31310.00,
                    'efectivo_pos_bs' => 1180.00,
                ],
            ],
        ];

        $conciliacion = $visionService->conciliarLoteCierre($fotos, $textoWhatsApp);

        $this->assertFalse($conciliacion['cuadre_conforme']);
        $this->assertNotEmpty($conciliacion['alertas']);

        // Verificar que detectó la discrepancia de 2000 Bs entre texto (14448) y papel (12448)
        $alertaTipeo = collect($conciliacion['alertas'])->firstWhere('tipo', 'error_tipeo_whatsapp');
        $this->assertNotNull($alertaTipeo);
        $this->assertEquals(2000.00, $alertaTipeo['diferencia_bs']);
    }

    public function test_escenario_22_despachar_informe_cierre_a_control_recaudo(): void
    {
        // Mockear servidor local HTTP Baileys en puerto 3001
        Http::fake([
            'http://127.0.0.1:3001/enviar-mensaje' => Http::response([
                'success' => true,
                'messageId' => 'BAE_MSG_999111',
            ], 200),
        ]);

        $barman = \App\Infrastructure\Persistence\Eloquent\Models\Usuario::create([
            'nombre' => 'Mariela Encargada',
            'apellido' => 'Moron',
            'codigo' => 'B01',
            'rol' => 'barman',
            'pin_hash' => bcrypt('1234'),
            'activo' => true,
        ]);

        $turno = Turno::create([
            'sucursal_id' => $this->sucursalBelen->id,
            'barman_id' => $barman->id,
            'tipo_turno' => 'dia',
            'estado' => 'cerrado',
            'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => now(),
        ]);

        PlanillaCaja::create([
            'turno_id' => $turno->id,
            'sucursal_id' => $this->sucursalBelen->id,
            'foto_url' => 'comprobantes/whatsapp/foto_cierre_belen.jpg',
            'fecha_operativa' => now()->toDateString(),
            'total_ventas_declaradas_bs' => 32690.00,
            'total_gastos_declarados_bs' => 18732.00,
            'monto_sobre_efectivo_bs' => 1510.00,
            'cajero_nombre' => 'Mariela',
            'estado_ocr' => 'procesado',
            'datos_ocr_json' => [
                'ventas_pos' => 32490.00,
                'ventas_qr_bs' => 31310.00,
                'qr_declarado_bs' => 12448.00,
            ],
        ]);

        $response = $this->postJson('/api/v1/whatsapp/despachar-informe-cierre', [
            'turno_id' => $turno->id,
            'forzar_reenvio' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'sucursal' => 'C22 - Casa Belén',
                'remote_jid' => '120363777@g.us',
                'ventas_pos' => 32490.00,
                'qr_pos' => 31310.00,
                'egresos_totales' => 18732.00,
                'efectivo_sobre' => 1510.00,
            ],
        ]);
    }

    public function test_config_control_recaudo_endpoint(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/config-control-recaudo');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'grupo_asignado' => [
                    'remote_jid' => '120363777@g.us',
                    'tipo_auditoria' => 'control_recaudo_global',
                ],
            ],
        ]);
    }
}
