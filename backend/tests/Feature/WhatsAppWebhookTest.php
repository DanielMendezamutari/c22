<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'puntofrio_wh_secret_2026_super';

    protected function setUp(): void
    {
        parent::setUp();
        putenv("WHATSAPP_WEBHOOK_SECRET={$this->secret}");
        $_ENV['WHATSAPP_WEBHOOK_SECRET'] = $this->secret;
    }

    public function test_rechaza_peticiones_sin_webhook_secret(): void
    {
        $response = $this->getJson('/api/v1/whatsapp/grupos-auditables');
        $response->assertStatus(401);
    }

    public function test_devuelve_grupos_auditables_con_secreto_valido(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Casa22 Central',
            'codigo' => 'C22',
            'activo' => true,
        ]);

        SucursalWhatsAppGrupo::create([
            'sucursal_id' => $sucursal->id,
            'remote_jid' => '1203630283921@g.us',
            'nombre_grupo' => '[C22] Cierres y Recaudación',
            'tipo_auditoria' => 'cierre_recaudacion',
            'activo' => true,
        ]);

        $response = $this->withHeader('X-Webhook-Secret', $this->secret)
            ->getJson('/api/v1/whatsapp/grupos-auditables');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'remote_jid' => '1203630283921@g.us',
                'sucursal_id' => $sucursal->id,
                'sucursal_nombre' => 'Casa22 Central',
            ]);
    }

    public function test_ingesta_webhook_resuelve_sucursal_deterministicamente(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Casa Coron',
            'codigo' => 'CORON',
            'activo' => true,
        ]);

        $grupo = SucursalWhatsAppGrupo::create([
            'sucursal_id' => $sucursal->id,
            'remote_jid' => '1203630999888@g.us',
            'nombre_grupo' => '[Coron] Cierres',
            'tipo_auditoria' => 'cierre_recaudacion',
            'activo' => true,
        ]);

        $payload = [
            'remote_jid' => '1203630999888@g.us',
            'sender_phone' => '59170123456',
            'sender_name' => 'Encargada Coron',
            'message_timestamp' => time(),
            'tipo' => 'imagen',
            'caption' => 'Planilla de cierre Coron',
            'media_base64' => 'data:image/jpeg;base64,' . base64_encode('fake-image-content'),
            'mimetype' => 'image/jpeg',
        ];

        $response = $this->withHeader('X-Webhook-Secret', $this->secret)
            ->postJson('/api/v1/webhook/whatsapp', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'pendiente_proceso',
                'sucursal_id' => $sucursal->id,
            ]);

        $this->assertDatabaseHas('whatsapp_mensajes_inbound', [
            'remote_jid' => '1203630999888@g.us',
            'sucursal_id' => $sucursal->id,
            'sender_phone' => '59170123456',
        ]);
    }

    public function test_confirmar_sucursal_manual_para_mensaje_sin_mapear(): void
    {
        $sucursal = Sucursal::create([
            'nombre' => 'Madan',
            'codigo' => 'MADAN',
            'activo' => true,
        ]);

        $inbound = WhatsAppMensajeInbound::create([
            'remote_jid' => '1203630000111@g.us',
            'sender_phone' => '59171234567',
            'sender_name' => 'Encargada Madan',
            'tipo_mensaje' => 'imagen',
            'media_path' => 'storage/comprobantes/test.jpg',
            'raw_text' => 'Comprobante Madan',
            'clasificacion_ia' => 'desconocido',
            'score_confianza' => 0.00,
            'estado' => 'requiere_confirmacion',
        ]);

        $response = $this->postJson('/api/v1/whatsapp/confirmar-sucursal', [
            'inbound_id' => $inbound->id,
            'sucursal_id' => $sucursal->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('whatsapp_mensajes_inbound', [
            'id' => $inbound->id,
            'sucursal_id' => $sucursal->id,
            'estado' => 'procesado',
        ]);
    }
}
