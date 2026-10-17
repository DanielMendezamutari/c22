<?php

namespace Tests\Feature;

use App\Application\UseCases\Cierre\NotificarLotesConciliadosUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppGrupoDescubierto;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Services\WhatsAppOutboundDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AvisoLoteCierreTest extends TestCase
{
    use RefreshDatabase;

    private LoteCierre $lote;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.conciliacion.aviso_grupo_jid' => 'contabilidad@g.us']);
        Http::preventStrayRequests();
        $casa = Sucursal::create(['nombre' => 'Casa de prueba', 'codigo' => 'PR', 'activo' => true]);
        $this->lote = LoteCierre::create([
            'sucursal_id' => $casa->id, 'fecha_operativa' => now()->toDateString(), 'tipo_turno' => 'noche',
            'estado' => 'conciliado', 'conciliado_at' => now(),
            'resultado' => ['estado' => 'CONFORME', 'reglas' => [], 'documentos_faltantes' => []],
        ]);
    }

    public function test_sin_config_o_jid_invalido_no_envia(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldNotReceive('enviarMensaje');
        foreach ([null, '', 'persona@s.whatsapp.net'] as $jid) {
            config(['services.conciliacion.aviso_grupo_jid' => $jid]);
            $this->assertSame(0, app(NotificarLotesConciliadosUseCase::class)->ejecutar());
        }
        $this->assertNull($this->lote->fresh()->aviso_enviado_at);
    }

    public function test_envia_una_vez_y_no_reenvia_con_misma_huella(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->once()
            ->with('contabilidad@g.us', Mockery::on(fn ($texto) => str_contains($texto, 'BORRADOR AUTOMÁTICO') && str_ends_with($texto, 'Ref. lote #'.$this->lote->id)), null, 'aviso_lote_cierre', $this->lote->sucursal_id, null)
            ->andReturn(['success' => true]);
        $caso = app(NotificarLotesConciliadosUseCase::class);
        $this->assertSame(1, $caso->ejecutar());
        $this->assertNotNull($this->lote->fresh()->aviso_enviado_at);
        $this->assertSame(64, strlen($this->lote->fresh()->aviso_huella));
        $this->assertSame(0, $caso->ejecutar());
        $this->lote->update(['resultado' => [...$this->lote->resultado, 'alertas' => ['Nueva alerta informativa']]]);
        $this->assertSame(0, $caso->ejecutar());
    }

    public function test_reenvia_como_actualizacion_si_cambian_estado_o_montos(): void
    {
        $mock = $this->mock(WhatsAppOutboundDispatcherService::class);
        $mock->shouldReceive('enviarMensaje')->once()->withArgs(fn ($jid, $texto) => str_contains($texto, 'BORRADOR AUTOMÁTICO'))->andReturn(['success' => true]);
        $mock->shouldReceive('enviarMensaje')->twice()->withArgs(fn ($jid, $texto) => str_contains($texto, '*ACTUALIZACIÓN*'))->andReturn(['success' => true]);
        $caso = app(NotificarLotesConciliadosUseCase::class);
        $this->assertSame(1, $caso->ejecutar());
        $anterior = $this->lote->fresh()->aviso_huella;
        $this->lote->update(['resultado' => ['estado' => 'REVISAR', 'reglas' => [
            ['codigo' => 'SOB', 'estado' => 'no_cumple', 'descripcion' => 'Sobre', 'esperado' => 100, 'declarado' => 90],
        ], 'documentos_faltantes' => []]]);
        $this->assertSame(1, $caso->ejecutar());
        $this->assertNotSame($anterior, $this->lote->fresh()->aviso_huella);
        $resultado = $this->lote->resultado;
        $resultado['reglas'][0]['declarado'] = 80;
        $this->lote->update(['resultado' => $resultado]);
        $this->assertSame(1, $caso->ejecutar());
    }

    public function test_reenvia_si_cambian_solo_los_documentos_faltantes(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->twice()->andReturn(['success' => true]);
        $caso = app(NotificarLotesConciliadosUseCase::class);
        $this->lote->update(['resultado' => ['estado' => 'REVISAR', 'reglas' => [], 'documentos_faltantes' => ['sobre']]]);
        $this->assertSame(1, $caso->ejecutar());
        $anterior = $this->lote->fresh()->aviso_huella;
        $this->lote->update(['resultado' => ['estado' => 'REVISAR', 'reglas' => [], 'documentos_faltantes' => ['reporte_pos']]]);
        $this->assertSame(1, $caso->ejecutar());
        $this->assertNotSame($anterior, $this->lote->fresh()->aviso_huella);
    }

    public function test_fallo_de_envio_no_marca_y_permite_reintento(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->twice()->andReturn(['success' => false], ['success' => true]);
        $caso = app(NotificarLotesConciliadosUseCase::class);
        $this->assertSame(0, $caso->ejecutar());
        $this->assertNull($this->lote->fresh()->aviso_huella);
        $this->assertNull($this->lote->fresh()->aviso_enviado_at);
        $this->assertSame(1, $caso->ejecutar());
    }

    public function test_ignora_lotes_antiguos_abiertos_y_bloqueados(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldNotReceive('enviarMensaje');
        $caso = app(NotificarLotesConciliadosUseCase::class);
        $this->lote->update(['conciliado_at' => now()->subHours(37)]);
        $this->assertSame(0, $caso->ejecutar());
        $this->lote->update(['conciliado_at' => now(), 'estado' => 'abierto']);
        $this->assertSame(0, $caso->ejecutar());
        $this->lote->update(['estado' => 'conciliado']);
        $lock = Cache::lock('aviso-lote:'.$this->lote->id, 120);
        $lock->get();
        try {
            $this->assertSame(0, $caso->ejecutar());
        } finally {
            $lock->release();
        }
    }

    public function test_no_avisa_cierres_anteriores_a_la_fecha_de_activacion(): void
    {
        config(['services.conciliacion.aviso_desde' => now()->addDay()->toDateString()]);
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldNotReceive('enviarMensaje');
        $this->assertSame(0, app(NotificarLotesConciliadosUseCase::class)->ejecutar());
    }

    public function test_usa_planilla_de_mayor_confianza(): void
    {
        $inbound = WhatsAppMensajeInbound::create(['remote_jid' => 'origen@g.us', 'sender_phone' => '59170000000', 'tipo_mensaje' => 'imagen', 'estado' => 'pendiente_proceso']);
        foreach ([[0.5, 50], [0.95, 1234.5]] as [$confianza, $total]) {
            $this->lote->documentos()->create(['inbound_id' => $inbound->id, 'tipo' => 'planilla_ventas', 'datos' => ['total' => $total], 'confianza' => $confianza]);
        }
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->once()
            ->withArgs(fn ($jid, $texto) => str_contains($texto, 'Venta total: Bs 1.234,50'))->andReturn(['success' => true]);
        $this->assertSame(1, app(NotificarLotesConciliadosUseCase::class)->ejecutar());
    }

    public function test_comando_respeta_sin_avisos_y_envia_si_esta_configurado(): void
    {
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->once()->andReturn(['success' => true]);
        $this->artisan('cierres:conciliar-pendientes --sin-avisos')->assertSuccessful();
        $this->assertNull($this->lote->fresh()->aviso_enviado_at);
        $this->artisan('cierres:conciliar-pendientes')->expectsOutput('Avisos enviados: 1')->assertSuccessful();
    }

    public function test_comando_prueba_lista_grupos_y_solo_envia_al_indicado(): void
    {
        WhatsAppGrupoDescubierto::create(['nombre_grupo' => 'Contabilidad prueba', 'remote_jid' => 'prueba@g.us']);
        $this->mock(WhatsAppOutboundDispatcherService::class)->shouldReceive('enviarMensaje')->once()
            ->with('prueba@g.us', '🧪 Prueba del bot C22: este grupo recibirá los borradores de cierre.', null, 'aviso_lote_cierre', null, null)->andReturn(['success' => true]);
        $this->artisan('cierres:aviso-prueba')->expectsTable(['Nombre', 'remote_jid'], [['Contabilidad prueba', 'prueba@g.us']])->assertSuccessful();
        $this->artisan('cierres:aviso-prueba --grupo=persona')->assertFailed();
        $this->artisan('cierres:aviso-prueba --grupo=prueba@g.us')->expectsOutput('Prueba enviada.')->assertSuccessful();
    }
}
