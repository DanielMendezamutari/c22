<?php

namespace Tests\Unit\Cierre;

use App\Infrastructure\AI\Cierre\EsquemaDocumentosCierre;
use App\Infrastructure\AI\Cierre\ExtractorDocumentosCierre;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtractorDocumentosCierreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'clave-de-prueba', 'services.gemini.model' => 'modelo-configurado']);
        Http::preventStrayRequests();
    }

    public function test_devuelve_planilla_y_sobre_normalizados(): void
    {
        $documentos = [
            ['tipo' => 'planilla_ventas', 'confianza' => 0.95, 'planilla_ventas' => [
                'encargada' => 'Nombre visible', 'fecha' => '2026-10-17', 'turno' => 'dia',
                'ingresos' => ['caja_chica' => 300, 'ventas_bebidas' => 1520.5],
                'efectivo' => 1000, 'faltante' => null,
            ]],
            ['tipo' => 'sobre', 'confianza' => 0.9, 'sobre' => [
                'nombre' => 'Nombre visible', 'fecha' => '2026-10-17', 'monto' => 1000, 'texto' => 'efectivo',
            ]],
        ];
        $this->simularJson(['documentos' => $documentos]);

        $resultado = $this->extraer();

        $this->assertSame([
            'ia_disponible' => true, 'modelo' => 'modelo-configurado', 'error' => null, 'documentos' => $documentos,
        ], $resultado);
        Http::assertSentCount(1);
    }

    public function test_normaliza_numeros_recursivamente_sin_modificar_textos(): void
    {
        $this->simularJson(['documentos' => [
            ['tipo' => 'planilla_ventas', 'confianza' => '0.95', 'planilla_ventas' => [
                'encargada' => '1.520', 'fecha' => '2026-10-17',
                'ingresos' => ['caja_chica' => 'Bs 300', 'ventas_bebidas' => '1.520'],
                'otros_ingresos' => [['concepto' => '1520,50', 'monto' => '1.520,50']],
                'garzones' => [['nombre' => 'Bs 300', 'total_vendido' => '1520,50', 'pagado' => '-26', 'firma' => 'qr']],
                'faltante' => null, 'sobrante' => '-', 'efectivo' => 'ilegible',
            ]],
            ['tipo' => 'ticket_chicas', 'ticket_chicas' => [
                'meseros' => ['1.520'], 'chicas' => [['nombre' => '12M', 'bebidas' => [
                    ['cantidad' => '3', 'producto' => '80', 'precio' => '80,50'],
                ]]],
            ]],
            ['tipo' => 'comprobante_transferencia', 'comprobante_transferencia' => [
                'monto' => 'Bs 300', 'origen_cuenta' => '001520', 'nro_operacion' => '000123',
            ]],
        ]]);

        $documentos = $this->extraer()['documentos'];
        $planilla = $documentos[0]['planilla_ventas'];
        $this->assertSame(0.95, $documentos[0]['confianza']);
        $this->assertSame(['caja_chica' => 300, 'ventas_bebidas' => 1520], $planilla['ingresos']);
        $this->assertSame([['concepto' => '1520,50', 'monto' => 1520.5]], $planilla['otros_ingresos']);
        $this->assertSame([['nombre' => 'Bs 300', 'total_vendido' => 1520.5, 'pagado' => -26, 'firma' => 'qr']], $planilla['garzones']);
        $this->assertSame('1.520', $planilla['encargada']);
        $this->assertSame('2026-10-17', $planilla['fecha']);
        $this->assertNull($planilla['faltante']);
        $this->assertSame(0, $planilla['sobrante']);
        $this->assertNull($planilla['efectivo']);
        $this->assertSame(['1.520'], $documentos[1]['ticket_chicas']['meseros']);
        $this->assertSame(['cantidad' => 3, 'producto' => '80', 'precio' => 80.5], $documentos[1]['ticket_chicas']['chicas'][0]['bebidas'][0]);
        $this->assertSame(['monto' => 300, 'origen_cuenta' => '001520', 'nro_operacion' => '000123'], $documentos[2]['comprobante_transferencia']);
    }

    public function test_429_agota_los_modelos_en_orden_y_devuelve_fallo(): void
    {
        Http::fake(['*' => Http::response([], 429)]);

        $resultado = $this->extraer();

        $this->assertFalse($resultado['ia_disponible']);
        $this->assertNull($resultado['modelo']);
        $this->assertSame([], $resultado['documentos']);
        $this->assertStringContainsString('429', $resultado['error']);
        $this->assertStringNotContainsString('clave-de-prueba', $resultado['error']);
        Http::assertSentCount(3);
        $urls = Http::recorded()->map(fn (array $par) => $par[0]->url())->all();
        foreach (['modelo-configurado', 'gemini-flash-latest', 'gemini-3.5-flash-lite'] as $indice => $modelo) {
            $this->assertStringContainsString('/models/'.$modelo.':generateContent?', $urls[$indice]);
        }
    }

    public function test_sin_api_key_no_envia_requests(): void
    {
        config(['services.gemini.api_key' => '']);
        Http::fake();

        $resultado = $this->extraer();

        $this->assertFalse($resultado['ia_disponible']);
        $this->assertNull($resultado['modelo']);
        $this->assertNotEmpty($resultado['error']);
        $this->assertSame([], $resultado['documentos']);
        Http::assertNothingSent();
    }

    public function test_request_incluye_esquema_mime_pdf_y_referencias(): void
    {
        $this->simularJson(['documentos' => []]);

        $resultado = (new ExtractorDocumentosCierre)->extraer(
            'data:application/pdf;base64,'.base64_encode('documento PDF'),
            'application/pdf', 'Contexto recibido', 'Casas recibidas', 'Personal recibido',
        );

        $this->assertTrue($resultado['ia_disponible']);
        Http::assertSent(function (Request $request): bool {
            $config = $request['generationConfig'];
            $partes = $request['contents'][0]['parts'];

            return $config['responseSchema'] === EsquemaDocumentosCierre::responseSchema()
                && $config['responseMimeType'] === 'application/json'
                && $config['temperature'] === 0
                && $partes[1]['inline_data'] === ['mime_type' => 'application/pdf', 'data' => base64_encode('documento PDF')]
                && str_contains($partes[0]['text'], 'Contexto recibido')
                && str_contains($partes[0]['text'], 'Casas recibidas')
                && str_contains($partes[0]['text'], 'Personal recibido');
        });
    }

    public function test_json_invalido_devuelve_fallo(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{inválido']]]]],
        ])]);

        $resultado = $this->extraer();

        $this->assertFalse($resultado['ia_disponible']);
        $this->assertNull($resultado['modelo']);
        $this->assertNotEmpty($resultado['error']);
        $this->assertSame([], $resultado['documentos']);
        Http::assertSentCount(3);
    }

    public function test_tipo_desconocido_se_normaliza_a_otro(): void
    {
        $this->simularJson(['documentos' => [['tipo' => 'billar', 'otro' => ['descripcion' => 'Joker 1.520']]]]);

        $resultado = $this->extraer();

        $this->assertTrue($resultado['ia_disponible']);
        $this->assertSame('otro', $resultado['documentos'][0]['tipo']);
        $this->assertSame('Joker 1.520', $resultado['documentos'][0]['otro']['descripcion']);
    }

    private function simularJson(array $contenido): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($contenido, JSON_THROW_ON_ERROR)]]]]],
        ])]);
    }

    private function extraer(): array
    {
        return (new ExtractorDocumentosCierre)->extraer('data:image/jpeg;base64,'.base64_encode('foto de prueba'), 'image/jpeg');
    }

    public function test_documento_sin_datos_no_cuenta_como_leido(): void
    {
        config(['services.gemini.api_key' => 'clave-test', 'services.gemini.model' => 'modelo-a']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['documentos' => [['tipo' => 'planilla_ventas', 'confianza' => 0.9]]])]]]]],
        ])]);

        $r = (new \App\Infrastructure\AI\Cierre\ExtractorDocumentosCierre())->extraer('data:image/jpeg;base64,'.base64_encode('x'), 'image/jpeg');

        $this->assertFalse($r['ia_disponible']);
        $this->assertSame([], $r['documentos']);
        \Illuminate\Support\Facades\Http::assertSentCount(3); // probó los tres modelos
    }
}
