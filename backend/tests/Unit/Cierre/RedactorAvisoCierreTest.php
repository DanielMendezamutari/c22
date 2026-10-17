<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\RedactorAvisoCierre;
use PHPUnit\Framework\TestCase;

class RedactorAvisoCierreTest extends TestCase
{
    private function redactar(array $resultado, ?array $planilla = null, bool $actualizacion = false): string
    {
        return (new RedactorAvisoCierre)->redactar('Casa de prueba', '2026-10-17', 'dia', null, $resultado, $planilla, $actualizacion);
    }

    public function test_faltante_se_muestra_como_revisar_sin_terminos_acusatorios(): void
    {
        $texto = $this->redactar(['estado' => 'FALTANTE', 'alertas' => ['FALTANTE, robo o pérdida por verificar']]);
        $this->assertStringContainsString('⚠️ Revisar', $texto);
        $this->assertDoesNotMatchRegularExpression('/\b(faltante|robo|pérdida)\b/iu', $texto);
        $this->assertStringContainsString('BORRADOR AUTOMÁTICO – EN PRUEBA', $texto);
        $this->assertStringContainsString('17/10/2026 · Día', $texto);
        $this->assertStringContainsString('Encargada: sin identificar', $texto);
    }

    public function test_montos_nulos_cero_y_diferencias_con_signo(): void
    {
        $texto = $this->redactar(['reglas' => [
            ['estado' => 'no_cumple', 'descripcion' => 'Sobre', 'esperado' => 1234.5, 'declarado' => 1200],
            ['estado' => 'no_cumple', 'descripcion' => 'QR', 'esperado' => 100, 'declarado' => 120],
        ]], ['total' => 1234.5, 'qr' => 0, 'linkser' => null, 'efectivo' => 1200, 'efectivo_en_sobre' => 1200]);
        $this->assertStringContainsString('💰 Venta total: Bs 1.234,50', $texto);
        $this->assertStringContainsString('💰 QR: Bs 0,00', $texto);
        $this->assertStringNotContainsString('Linkser:', $texto);
        $this->assertStringContainsString('esperado Bs 1.234,50, declarado Bs 1.200,00 (dif. -34,50)', $texto);
        $this->assertStringContainsString('(dif. +20,00)', $texto);
    }

    public function test_traduce_documentos_faltantes(): void
    {
        $texto = $this->redactar(['documentos_faltantes' => ['planilla_ventas', 'reporte_pos', 'pago_chicas', 'control_shampagne', 'sobre', 'comprobante_transferencia', 'otro_documento']]);
        $this->assertStringContainsString('Falta enviar: planilla, reporte POS, hoja de chicas, control de shampagne, foto del sobre, comprobante QR, otro documento', $texto);
    }

    public function test_conforme_y_pendiente_se_traducen(): void
    {
        $this->assertStringContainsString('✅ Cuadra', $this->redactar(['estado' => 'CONFORME']));
        $this->assertStringContainsString('🟡 Cuadra (comisiones se verifican al cerrar todas las casas)', $this->redactar(['estado' => 'CUADRA_PENDIENTE']));
        $this->assertStringContainsString('🔄 *ACTUALIZACIÓN* – BORRADOR EN PRUEBA', $this->redactar([], null, true));
    }

    public function test_limita_puntos_y_alertas_e_incluye_detalles_sin_montos(): void
    {
        $texto = $this->redactar([
            'reglas' => array_fill(0, 12, ['estado' => 'sin_verificar', 'descripcion' => 'Comprobante', 'detalle' => 'Foto ilegible']),
            'alertas' => array_map(fn ($n) => "Alerta {$n}", range(1, 6)),
        ]);
        $this->assertSame(10, substr_count($texto, '• Comprobante: Foto ilegible'));
        $this->assertStringContainsString('…y 2 más', $texto);
        $this->assertSame(5, substr_count($texto, 'ℹ️ '));
        $this->assertStringNotContainsString('Alerta 6', $texto);
    }
}
