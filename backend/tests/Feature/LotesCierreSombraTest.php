<?php

namespace Tests\Feature;

use App\Application\UseCases\Cierre\ConciliarLotesListosUseCase;
use App\Application\UseCases\Cierre\RegistrarDocumentosUseCase;
use App\Infrastructure\AI\Cierre\ExtractorDocumentosCierre;
use App\Infrastructure\Persistence\Eloquent\Models\DocumentoCierre;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Jobs\ProcesarDocumentosCierreJob;
use App\Jobs\ProcesarMensajeWhatsAppJob;
use App\Services\GeminiVisionAuditorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LotesCierreSombraTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $casa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
        $this->travelTo(Carbon::parse('2026-10-17 13:00:00', 'UTC'));
        config(['services.conciliacion.modo' => 'sombra', 'services.conciliacion.minutos_espera' => 10]);
        Http::preventStrayRequests();
        Http::fake();
        $this->casa = Sucursal::create(['nombre' => 'Belén', 'codigo' => 'BE', 'alias_documentos' => 'belen', 'activo' => true]);
        SucursalWhatsAppGrupo::create(['sucursal_id' => $this->casa->id, 'remote_jid' => 'casa@g.us', 'nombre_grupo' => 'Casa', 'activo' => true, 'es_global' => false]);
        SucursalWhatsAppGrupo::create(['remote_jid' => 'global@g.us', 'nombre_grupo' => 'Global', 'activo' => true, 'es_global' => true]);
    }

    public function test_planilla_pos_y_comprobante_global_del_remitente_comparten_lote(): void
    {
        $comprobante = $this->inbound(['remote_jid' => 'global@g.us']);
        $planilla = $this->inbound();
        $pos = $this->inbound();
        $this->extraer(
            ['comprobante_transferencia', ['monto' => 100, 'destino_cuenta' => '123']],
            ['planilla_ventas', $this->planilla()],
            ['reporte_pos', ['servidor' => 'BELEN', 'desde' => '2026-10-16 19:00:00']],
        );
        $this->registrar($comprobante);
        $this->assertNull(DocumentoCierre::first()->lote_id);
        $this->registrar($planilla);
        $this->registrar($pos);
        $this->assertSame(1, LoteCierre::count());
        $this->assertSame(3, LoteCierre::first()->documentos()->count());
        $this->assertSame('remitente', DocumentoCierre::where('inbound_id', $comprobante->id)->first()->evidencia_casa);
        $this->assertSame([$planilla->sender_phone], LoteCierre::first()->remitentes);
    }

    public function test_encabezado_pos_gana_al_grupo(): void
    {
        $corona = Sucursal::create(['nombre' => 'Corona', 'codigo' => 'CO', 'alias_documentos' => 'corona', 'activo' => true]);
        $this->extraer(['reporte_pos', ['servidor' => 'CORONA CASA22', 'desde' => '2026-10-16 20:00']]);
        $this->registrar($this->inbound());
        $doc = DocumentoCierre::first();
        $this->assertSame($corona->id, $doc->sucursal_id);
        $this->assertSame('encabezado', $doc->evidencia_casa);
        $this->assertSame($corona->id, $doc->lote->sucursal_id);
    }

    public function test_noche_enviada_a_las_nueve_es_del_dia_anterior(): void
    {
        $this->extraer(['planilla_ventas', ['turno' => 'noche', 'fecha' => '2023-10-26']]);
        $this->registrar($this->inbound());
        $this->assertSame('2026-10-16', DocumentoCierre::first()->fecha_operativa->toDateString());
    }

    public function test_espera_reapertura_y_resultado_con_estado_y_reglas(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()], ['sobre', ['monto' => 100]]);
        $this->registrar($this->inbound());
        $conciliar = app(ConciliarLotesListosUseCase::class);
        $this->assertSame(0, $conciliar->ejecutar());
        $this->travel(9)->minutes();
        $this->assertSame(0, $conciliar->ejecutar());
        $this->travel(1)->minutes();
        $this->assertSame(1, $conciliar->ejecutar());
        $lote = LoteCierre::first();
        $this->assertSame('conciliado', $lote->estado);
        $this->assertArrayHasKey('estado', $lote->resultado);
        $this->assertNotEmpty($lote->resultado['reglas']);
        $this->assertArrayHasKey('alertas_armado', $lote->resultado);
        $this->assertSame(0, $conciliar->ejecutar());
        $this->registrar($this->inbound());
        $this->assertSame('abierto', $lote->fresh()->estado);
        $this->assertNull($lote->fresh()->conciliado_at);
        $this->assertSame(0, $conciliar->ejecutar());
        $this->travel(10)->minutes();
        $this->assertSame(1, $conciliar->ejecutar());
    }

    public function test_off_no_despacha_y_sombra_y_activo_si(): void
    {
        Bus::fake([ProcesarDocumentosCierreJob::class]);
        $this->mock(GeminiVisionAuditorService::class, fn ($m) => $m->shouldReceive('auditarYClasificar')->andReturn([
            'clasificacion' => 'otro', 'score_confianza' => 0.9, 'datos' => [],
        ]));
        config(['services.conciliacion.modo' => 'off']);
        app()->call([new ProcesarMensajeWhatsAppJob($this->inbound()->id), 'handle']);
        Bus::assertNotDispatched(ProcesarDocumentosCierreJob::class);
        foreach (['sombra', 'activo'] as $modo) {
            config(['services.conciliacion.modo' => $modo]);
            $inbound = $this->inbound();
            app()->call([new ProcesarMensajeWhatsAppJob($inbound->id), 'handle']);
            Bus::assertDispatched(ProcesarDocumentosCierreJob::class, fn ($j) => $j->inboundId === $inbound->id);
        }
    }

    public function test_sombra_no_escribe_contabilidad_ni_envia_http(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()]);
        $inbound = $this->inbound();
        $antes = $inbound->fresh()->toArray();
        app()->call([new ProcesarDocumentosCierreJob($inbound->id), 'handle']);
        $this->travel(10)->minutes();
        app(ConciliarLotesListosUseCase::class)->ejecutar();
        foreach (['planillas_caja', 'turnos', 'gastos_caja_chica', 'whatsapp_mensajes_outbound'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
        $this->assertSame($antes, $inbound->fresh()->toArray());
        Http::assertNothingSent();
    }

    public function test_duplicado_se_ignora(): void
    {
        $this->mock(ExtractorDocumentosCierre::class, fn ($m) => $m->shouldNotReceive('extraer'));
        $this->registrar($this->inbound(['estado' => 'duplicado']));
        $this->assertDatabaseCount('documentos_cierre', 0);
    }

    public function test_registro_idempotente_y_sin_ia_no_inventa_documentos(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()]);
        $inbound = $this->inbound();
        $this->registrar($inbound);
        $this->registrar($inbound);
        $this->assertDatabaseCount('documentos_cierre', 1);
        $this->mock(ExtractorDocumentosCierre::class, fn ($m) => $m->shouldReceive('extraer')->once()->andReturn(['ia_disponible' => false, 'documentos' => []]));
        $this->registrar($this->inbound());
        $this->assertDatabaseCount('documentos_cierre', 1);
    }

    public function test_texto_normalizado_y_alias_sin_casa_queda_pendiente(): void
    {
        $this->mock(ExtractorDocumentosCierre::class, fn ($m) => $m->shouldNotReceive('extraer'));
        $this->registrar($this->inbound(['media_path' => null, 'raw_text' => 'VÉNTA total 100 QR 0 EFECTIVO 100']));
        $doc = DocumentoCierre::first();
        $this->assertSame('texto', $doc->tipo);
        $this->assertSame('grupo', $doc->evidencia_casa);
        $this->registrar($this->inbound(['media_path' => null, 'remote_jid' => 'global@g.us', 'sender_phone' => 'otro', 'raw_text' => 'venta total 20']));
        $this->assertNull(DocumentoCierre::latest('id')->first()->lote_id);
        $this->registrar($this->inbound(['media_path' => null, 'remote_jid' => 'global@g.us', 'sender_phone' => 'tercero', 'raw_text' => 'venta belen 20']));
        $this->assertSame('texto', DocumentoCierre::latest('id')->first()->evidencia_casa);
    }

    public function test_encargada_registrada_gana_al_alias_del_texto(): void
    {
        DB::statement('PRAGMA ignore_check_constraints = ON');
        Usuario::create(['nombre' => 'Ana', 'apellido' => 'Perez', 'rol' => 'cajera', 'pin_hash' => 'test', 'activo' => true, 'sucursal_actual_id' => $this->casa->id]);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        Sucursal::create(['nombre' => 'Otra', 'codigo' => 'OT', 'alias_documentos' => 'otra', 'activo' => true]);
        $this->extraer(['planilla_ventas', [...$this->planilla(), 'encargada' => 'Ana Perez']]);
        $this->registrar($this->inbound(['remote_jid' => 'global@g.us', 'raw_text' => 'otra']));
        $this->assertSame('encargada', DocumentoCierre::first()->evidencia_casa);
        $this->assertSame($this->casa->id, DocumentoCierre::first()->sucursal_id);
    }

    public function test_lock_de_lote_ocupado_no_concilia(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()]);
        $this->registrar($this->inbound());
        $this->travel(10)->minutes();
        $lock = Cache::lock('cierre-lote:'.LoteCierre::first()->id, 60);
        $lock->get();
        $this->assertSame(0, app(ConciliarLotesListosUseCase::class)->ejecutar());
        $lock->release();
        $this->assertSame(1, app(ConciliarLotesListosUseCase::class)->ejecutar());
    }

    public function test_comandos_reprocesan_rango_boliviano_y_muestran_lote(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()]);
        $this->inbound();
        $this->inbound(['created_at' => now()->subDays(3)]);
        $this->artisan('cierres:reprocesar-sombra', ['--desde' => '2026-10-17 08:59', '--hasta' => '2026-10-17 09:01'])
            ->expectsOutputToContain('Mensajes procesados: 1. Lotes conciliados: 1.')->assertSuccessful();
        $this->assertSame(10, config('services.conciliacion.minutos_espera'));
        $this->artisan('cierres:ver', ['lote_id' => LoteCierre::first()->id])->expectsOutputToContain('planilla_ventas')->assertSuccessful();
        $this->artisan('cierres:conciliar-pendientes')->expectsOutput('Lotes conciliados: 0')->assertSuccessful();
    }

    public function test_varios_documentos_pdf_reciben_contexto_casas_y_personal_activo(): void
    {
        Usuario::create(['nombre' => 'Luis', 'apellido' => 'Barra', 'rol' => 'barman', 'pin_hash' => 'test', 'activo' => true, 'sucursal_actual_id' => $this->casa->id]);
        $this->mock(ExtractorDocumentosCierre::class, function ($mock) {
            $mock->shouldReceive('extraer')->once()->withArgs(fn ($ruta, $mime, $contexto, $casas, $personal) => $mime === 'application/pdf' && str_contains($contexto, 'Adjunto cierre')
                && str_contains($casas, 'belen') && str_contains($personal, 'Luis Barra (barman)')
            )->andReturn(['ia_disponible' => true, 'documentos' => [
                ['tipo' => 'planilla_ventas', 'planilla_ventas' => $this->planilla(), 'confianza' => 0.9],
                ['tipo' => 'sobre', 'sobre' => ['monto' => 100], 'confianza' => 0.8],
            ]]);
        });
        $this->registrar($this->inbound(['media_path' => 'storage/cierre.PDF', 'raw_text' => 'Adjunto cierre']));
        $this->assertSame(2, LoteCierre::first()->documentos()->count());
    }

    public function test_remitente_no_hereda_casa_pasadas_16_horas(): void
    {
        $this->extraer(['planilla_ventas', $this->planilla()], ['comprobante_transferencia', ['monto' => 100]]);
        $this->registrar($this->inbound());
        $this->travel(17)->hours();
        $this->registrar($this->inbound(['remote_jid' => 'global@g.us']));
        $this->assertNull(DocumentoCierre::latest('id')->first()->lote_id);
    }

    public function test_sin_planilla_no_concilia_y_planillas_distintas_requieren_revision(): void
    {
        $this->extraer(['sobre', ['monto' => 100]], ['planilla_ventas', $this->planilla()], ['planilla_ventas', [...$this->planilla(), 'total_ingresos' => 200]]);
        $this->registrar($this->inbound());
        $this->travel(10)->minutes();
        $this->assertSame(0, app(ConciliarLotesListosUseCase::class)->ejecutar());
        $this->registrar($this->inbound());
        $this->registrar($this->inbound());
        $this->travel(10)->minutes();
        $this->assertSame(1, app(ConciliarLotesListosUseCase::class)->ejecutar());
        $resultado = LoteCierre::first()->resultado;
        $this->assertSame('REVISAR', $resultado['estado']);
        $this->assertStringContainsString('Dos planillas distintas', $resultado['alertas_armado'][0]);
    }

    public function test_fallo_al_despachar_no_rompe_el_job_actual(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Cola no disponible'));
        $this->mock(GeminiVisionAuditorService::class, fn ($m) => $m->shouldReceive('auditarYClasificar')->andReturn([
            'clasificacion' => 'otro', 'score_confianza' => 0.9, 'datos' => [],
        ]));
        $inbound = $this->inbound(['sucursal_id' => $this->casa->id]);
        app()->call([new ProcesarMensajeWhatsAppJob($inbound->id), 'handle']);
        $this->assertSame('procesado', $inbound->fresh()->estado);
    }

    private function inbound(array $datos = []): WhatsAppMensajeInbound
    {
        $inbound = new WhatsAppMensajeInbound([
            'remote_jid' => 'casa@g.us', 'sender_phone' => '59170000001', 'sender_name' => 'Encargada',
            'tipo_mensaje' => 'imagen', 'media_path' => 'storage/cierre.jpg', 'estado' => 'pendiente_proceso',
        ]);
        $inbound->forceFill($datos)->save();

        return $inbound;
    }

    private function registrar(WhatsAppMensajeInbound $inbound): void
    {
        app(RegistrarDocumentosUseCase::class)->ejecutar($inbound->id);
    }

    private function extraer(array ...$docs): void
    {
        $this->mock(ExtractorDocumentosCierre::class, function ($mock) use ($docs) {
            $expectativa = $mock->shouldReceive('extraer')->times(count($docs));
            $expectativa->andReturnValues(array_map(fn ($d) => ['ia_disponible' => true, 'documentos' => [
                ['tipo' => $d[0], $d[0] => $d[1], 'confianza' => 0.95],
            ]], $docs));
        });
    }

    private function planilla(): array
    {
        return ['encargada' => 'Ana', 'fecha' => '2026-10-16', 'turno' => 'noche',
            'ingresos' => ['ventas_bebidas' => 100], 'total_ingresos' => 100,
            'egresos' => ['otros' => 0], 'total_egresos' => 0, 'total' => 100, 'efectivo' => 100, 'qr' => 0];
    }

    public function test_fecha_del_pos_se_lee_como_dia_mes_anio(): void
    {
        $f = \App\Application\UseCases\Cierre\AsignarLoteUseCase::class;

        $this->assertSame('2026-10-16 21:05', $f::fechaBoliviana('16/10/2026 21:05')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-03 09:15', $f::fechaBoliviana('03/10/2026 9:15')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-03 09:15', $f::fechaBoliviana('03/10/2026 09:15')->format('Y-m-d H:i'));
        $this->assertNull($f::fechaBoliviana('16/13/2026'));
        $this->assertSame('2026-10-17', $f::fechaBoliviana('2026-10-17')->format('Y-m-d'));
        $this->assertNull($f::fechaBoliviana('ayer en la noche'));
    }
}
