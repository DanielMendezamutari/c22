<?php

namespace Tests\Feature;

use App\Application\UseCases\Auditoria\GenerarYDespacharInformeAuditoriaUseCase;
use App\Application\UseCases\Turnos\CerrarTurnoUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Jobs\ProcesarMensajeWhatsAppJob;
use App\Services\GeminiVisionAuditorService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Spec 004: el informe de recaudo nace solo de la planilla de la encargada,
 * nunca del cierre del barman ni de datos inventados.
 */
class InformeCierreEncargadaTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_URL = 'http://127.0.0.1:3001/enviar-mensaje';

    private Sucursal $casa;
    private Usuario $barman;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.webhook_secret' => 'test-secret']);
        Http::fake([self::BOT_URL => Http::response(['success' => true, 'messageId' => 'BOT_MSG_1'], 200)]);

        $this->casa = Sucursal::create(['nombre' => 'Casa22', 'codigo' => 'C22', 'activo' => true]);
        $this->barman = Usuario::create([
            'nombre' => 'Sergio', 'apellido' => 'Barra', 'codigo' => 'B01', 'rol' => 'barman',
            'pin_hash' => bcrypt('1234'), 'activo' => true, 'sucursal_actual_id' => $this->casa->id,
        ]);
        SucursalWhatsAppGrupo::create([
            'sucursal_id' => null, 'remote_jid' => '120363777@g.us', 'nombre_grupo' => 'Control de recaudado',
            'tipo_auditoria' => 'control_recaudo_global', 'es_global' => true, 'activo' => true,
        ]);
    }

    public function test_cierre_del_barman_en_la_app_no_despacha_informe(): void
    {
        $turno = $this->crearTurno('abierto');

        app(CerrarTurnoUseCase::class)->ejecutar($turno->id, []);

        $this->assertSame('cerrado', $turno->fresh()->estado);
        Http::assertNothingSent();
    }

    public function test_sin_planilla_de_encargada_no_hay_informe(): void
    {
        $turno = $this->crearTurno('cerrado');

        $this->expectException(DomainException::class);
        app(GenerarYDespacharInformeAuditoriaUseCase::class)->ejecutar($turno->id, true);
    }

    public function test_planilla_de_encargada_despacha_informe_con_su_nombre_y_no_el_del_barman(): void
    {
        $this->simularIa($this->planillaIa('Rosa Encargada', 8500));
        $inbound = $this->crearInbound('Rosa');

        ProcesarMensajeWhatsAppJob::dispatchSync($inbound->id);

        $inbound->refresh();
        $this->assertSame('planilla_caja', $inbound->clasificacion_ia);
        $this->assertSame('procesado', $inbound->estado);
        $this->assertSame(8500.0, (float) PlanillaCaja::where('turno_id', $inbound->turno_id)->value('total_ventas_declaradas_bs'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === self::BOT_URL
            && str_contains($r['texto'], 'Rosa Encargada')
            && !str_contains($r['texto'], 'Sergio'));
    }

    public function test_segunda_planilla_del_turno_no_sobrescribe_ni_vuelve_a_despachar(): void
    {
        $this->simularIa($this->planillaIa('Rosa Encargada', 8500), $this->planillaIa('Otra Persona', 999));
        $primera = $this->crearInbound('Rosa');
        ProcesarMensajeWhatsAppJob::dispatchSync($primera->id);
        $segunda = $this->crearInbound('Otra');
        ProcesarMensajeWhatsAppJob::dispatchSync($segunda->id);

        $turnoId = $primera->fresh()->turno_id;
        $this->assertSame($turnoId, $segunda->fresh()->turno_id);
        $this->assertSame('requiere_revision', $segunda->fresh()->estado);
        $planilla = PlanillaCaja::where('turno_id', $turnoId)->first();
        $this->assertSame(8500.0, (float) $planilla->total_ventas_declaradas_bs);
        $this->assertSame('Rosa Encargada', $planilla->cajero_nombre);
        Http::assertSentCount(1);
    }

    public function test_cierre_de_barra_se_registra_sin_cerrar_turno_ni_despachar(): void
    {
        $turno = $this->crearTurno('abierto');
        $this->simularIa(['clasificacion' => 'cierre_barra', 'score_confianza' => 0.9, 'sucursal_sugerida' => 'Casa22', 'datos' => ['es_cierre_turno' => true]]);
        $inbound = $this->crearInbound('Sergio');

        ProcesarMensajeWhatsAppJob::dispatchSync($inbound->id);

        $this->assertSame('cierre_barra', $inbound->fresh()->clasificacion_ia);
        $this->assertSame('abierto', $turno->fresh()->estado);
        $this->assertSame(0, PlanillaCaja::count());
        Http::assertNothingSent();
    }

    public function test_si_la_ia_no_esta_disponible_no_se_inventan_cifras(): void
    {
        config(['services.gemini.api_key' => null]);
        $turno = $this->crearTurno('abierto');
        $inbound = $this->crearInbound('Rosa');

        ProcesarMensajeWhatsAppJob::dispatchSync($inbound->id);

        $inbound->refresh();
        $this->assertSame('requiere_revision', $inbound->estado);
        $this->assertEquals(0, $inbound->score_confianza);
        $this->assertNull($inbound->turno_id);
        $this->assertSame('abierto', $turno->fresh()->estado);
        $this->assertSame(0, PlanillaCaja::count());
        Http::assertNothingSent();
    }

    public function test_ticket_vip_alemana_casa22_se_asigna_a_casa_22_vip_y_no_a_belen(): void
    {
        $vip = Sucursal::create(['nombre' => 'casa22vip', 'codigo' => 'C22VIP', 'activo' => true]);
        $inbound = $this->crearInbound('Fanny');

        $id = ProcesarMensajeWhatsAppJob::inferirSucursalId(
            ['sucursal_sugerida' => null, 'datos' => ['servidor_cajero' => 'VIP ALEMANA CASA22 - V.491']],
            $inbound,
        );

        $this->assertSame($vip->id, $id);
    }

    public function test_el_prompt_usa_el_personal_registrado_y_no_nombres_fijos(): void
    {
        // El rol 'cajera' se agrega con ALTER solo en MySQL; en SQLite de tests se omite el CHECK del enum
        \Illuminate\Support\Facades\DB::statement('PRAGMA ignore_check_constraints = ON');
        Usuario::create([
            'nombre' => 'Rosa', 'apellido' => 'Encargada', 'codigo' => 'C01', 'rol' => 'cajera',
            'pin_hash' => bcrypt('5678'), 'activo' => true, 'sucursal_actual_id' => $this->casa->id,
        ]);
        \Illuminate\Support\Facades\DB::statement('PRAGMA ignore_check_constraints = OFF');
        config(['services.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($this->planillaIa('Rosa Encargada', 100))]]]]],
        ], 200)]);

        $resultado = app(GeminiVisionAuditorService::class)->auditarYClasificar('data:image/jpeg;base64,/9j/AA==');

        $this->assertSame('planilla_caja', $resultado['clasificacion']);
        Http::assertSent(function (Request $r) {
            if (!str_contains($r->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }
            $prompt = $r['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Encargadas/cajeras: Rosa Encargada')
                && str_contains($prompt, 'Barmans/garzones (NO encargados): Sergio Barra')
                && str_contains($prompt, '"cierre_barra"')
                && !preg_match('/FANNY|SISIEL|MABEL/i', $prompt);
        });
    }

    public function test_foto_tardia_del_mismo_cierre_reutiliza_el_turno_sin_otro_informe(): void
    {
        // La IA responde "Día" con tilde; antes rompía el enum 'dia'/'noche' de turnos
        $this->simularIa($this->planillaIa('Rosa Encargada', 8500, 'Día'), $this->planillaIa('Rosa Encargada', 8500, 'Día'));
        $primera = $this->crearInbound('Rosa');
        ProcesarMensajeWhatsAppJob::dispatchSync($primera->id);

        $this->travel(3)->hours();
        $tardia = $this->crearInbound('Rosa');
        ProcesarMensajeWhatsAppJob::dispatchSync($tardia->id);

        $turno = Turno::find($primera->fresh()->turno_id);
        $this->assertSame('dia', $turno->tipo_turno);
        $this->assertSame($turno->id, $tardia->fresh()->turno_id);
        $this->assertSame('requiere_revision', $tardia->fresh()->estado);
        $this->assertSame(1, Turno::count());
        Http::assertSentCount(1);
    }

    public function test_carga_web_sin_ia_y_sin_montos_manuales_se_rechaza(): void
    {
        config(['services.gemini.api_key' => null]);

        $response = $this->postJson('/api/v1/recaudaciones/procesar-planilla', [
            'sucursal_id' => $this->casa->id,
            'fecha_operativa' => now()->toDateString(),
            'foto_base64' => 'data:image/jpeg;base64,/9j/AA==',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertSame(0, PlanillaCaja::count());
    }

    private function crearTurno(string $estado): Turno
    {
        return Turno::create([
            'sucursal_id' => $this->casa->id,
            'barman_id' => $this->barman->id,
            'tipo_turno' => now()->hour >= 6 && now()->hour < 18 ? 'dia' : 'noche',
            'estado' => $estado,
            'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => $estado === 'cerrado' ? now() : null,
        ]);
    }

    private function crearInbound(string $remitente): WhatsAppMensajeInbound
    {
        return WhatsAppMensajeInbound::create([
            'sucursal_id' => $this->casa->id,
            'remote_jid' => '120363001@g.us',
            'sender_phone' => '268263925760142',
            'sender_name' => $remitente,
            'tipo_mensaje' => 'imagen',
            'media_path' => 'comprobantes/whatsapp/cierre.jpg',
            'estado' => 'pendiente_proceso',
        ]);
    }

    private function planillaIa(string $encargada, float $ventas, ?string $turno = null): array
    {
        return [
            'clasificacion' => 'planilla_caja',
            'score_confianza' => 0.95,
            'sucursal_sugerida' => 'Casa22',
            'datos' => [
                'es_cierre_turno' => true,
                'total_ventas_declaradas_bs' => $ventas,
                'total_gastos_declarados_bs' => 100,
                'monto_sobre_efectivo_bs' => $ventas - 100,
                'cajero_nombre' => $encargada,
                'fecha' => now()->toDateString(),
                'turno' => $turno,
            ],
        ];
    }

    private function simularIa(array ...$respuestas): void
    {
        $this->mock(GeminiVisionAuditorService::class, function ($mock) use ($respuestas) {
            $mock->shouldReceive('auditarYClasificar')->andReturnValues($respuestas);
        });
    }
}
