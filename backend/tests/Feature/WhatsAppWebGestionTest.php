<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppGrupoDescubierto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsAppWebGestionTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'puntofrio_wh_secret_2026_super';

    protected function setUp(): void
    {
        parent::setUp();
        putenv("WHATSAPP_WEBHOOK_SECRET={$this->secret}");
        $_ENV['WHATSAPP_WEBHOOK_SECRET'] = $this->secret;
        Cache::flush();
    }

    public function test_actualizar_y_consultar_estado_del_bot_con_qr(): void
    {
        $qrDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        // 1. Bot envía actualización de QR a Laravel
        $resBot = $this->withHeader('X-Webhook-Secret', $this->secret)
            ->postJson('/api/v1/whatsapp/bot-status', [
                'estado' => 'esperando_qr',
                'qr_code_data_url' => $qrDataUrl,
                'telefono' => null,
            ]);

        $resBot->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Estado del bot actualizado exitosamente',
            ]);

        // 2. Frontend Web consulta el estado del bot
        $resWeb = $this->getJson('/api/v1/whatsapp/bot-status');
        $resWeb->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'estado' => 'esperando_qr',
                    'qr_code_data_url' => $qrDataUrl,
                    'telefono' => null,
                ],
            ]);
    }

    public function test_sincronizar_y_listar_grupos_descubiertos_con_vinculacion(): void
    {
        // 1. Microservicio sincroniza 2 grupos detectados
        $resSincronizar = $this->withHeader('X-Webhook-Secret', $this->secret)
            ->postJson('/api/v1/whatsapp/grupos-descubiertos', [
                'grupos' => [
                    [
                        'jid' => '1203630283921@g.us',
                        'name' => '[C22] Cierres y Recaudación',
                        'participants' => 8,
                    ],
                    [
                        'jid' => '1203630987654@g.us',
                        'name' => '[Madan] Cierres de Turno',
                        'participants' => 6,
                    ],
                ],
            ]);

        $resSincronizar->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_sincronizados' => 2,
            ]);

        $this->assertDatabaseHas('whatsapp_grupos_descubiertos', [
            'remote_jid' => '1203630283921@g.us',
            'nombre_grupo' => '[C22] Cierres y Recaudación',
            'participantes_count' => 8,
        ]);

        // 2. Consulta web de grupos disponibles (inicialmente sin vincular)
        $resDisponibles = $this->getJson('/api/v1/whatsapp/grupos-disponibles');
        $resDisponibles->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $resDisponibles->json('data');
        $this->assertCount(2, $data);
        $this->assertFalse($data[0]['vinculado']);
    }

    public function test_vincular_grupo_descubierto_a_sucursal_mediante_crud(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Casa 22 Central',
            'codigo' => 'C22',
            'activo' => true,
        ]);

        WhatsAppGrupoDescubierto::create([
            'remote_jid' => '1203630283921@g.us',
            'nombre_grupo' => '[C22] Cierres y Recaudación',
            'participantes_count' => 8,
        ]);

        // Guardar vinculación desde la Web
        $resVincular = $this->postJson('/api/v1/sucursal-whatsapp-grupos', [
            'sucursal_id' => $sucursal->id,
            'remote_jid' => '1203630283921@g.us',
            'nombre_grupo' => '[C22] Cierres y Recaudación',
            'tipo_auditoria' => 'cierre_recaudacion',
            'activo' => true,
        ]);

        $resVincular->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'remote_jid' => '1203630283921@g.us',
                    'sucursal_id' => $sucursal->id,
                    'tipo_auditoria' => 'cierre_recaudacion',
                    'activo' => true,
                ],
            ]);

        $this->assertDatabaseHas('sucursal_whatsapp_grupos', [
            'remote_jid' => '1203630283921@g.us',
            'sucursal_id' => $sucursal->id,
        ]);

        // Verificar que ahora grupos-disponibles lo marque como vinculado
        $resDisponibles = $this->getJson('/api/v1/whatsapp/grupos-disponibles');
        $resDisponibles->assertStatus(200);

        $grupo = collect($resDisponibles->json('data'))->firstWhere('remote_jid', '1203630283921@g.us');
        $this->assertTrue($grupo['vinculado']);
        $this->assertEquals($sucursal->id, $grupo['sucursal_id']);
        $this->assertEquals('Casa 22 Central', $grupo['sucursal_nombre']);
    }

    public function test_solicitar_desconexion_del_bot(): void
    {
        $resDesconectar = $this->postJson('/api/v1/whatsapp/desconectar');
        $resDesconectar->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $resStatus = $this->getJson('/api/v1/whatsapp/bot-status');
        $resStatus->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'estado' => 'desconectado',
                    'desconectar_solicitado' => true,
                ],
            ]);
    }
}
