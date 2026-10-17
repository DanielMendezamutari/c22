<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\EvaluadorExtraccion;
use PHPUnit\Framework\TestCase;

class EvaluadorExtraccionTest extends TestCase
{
    private const ESPERADO = [[
        'tipo' => 'planilla_ventas',
        'planilla_ventas' => [
            'encargada' => 'Ariane Mercado',
            'garzones' => [['nombre' => 'Micky', 'pagado' => 31], ['nombre' => 'Rudy', 'pagado' => 20]],
            'total_ingresos' => 890,
            'qr' => -26,
        ],
    ]];

    public function test_lectura_perfecta(): void
    {
        $r = (new EvaluadorExtraccion())->evaluar(self::ESPERADO, self::ESPERADO);

        $this->assertSame(7, $r['campos']);
        $this->assertSame(7, $r['campos_ok']);
        $this->assertSame([], $r['errores']);
    }

    public function test_cuenta_cada_error_con_su_campo(): void
    {
        $leido = self::ESPERADO;
        $leido[0]['planilla_ventas']['qr'] = 26;               // signo perdido
        $leido[0]['planilla_ventas']['garzones'][1]['pagado'] = '20.00'; // string numérico equivalente: bien
        $leido[0]['planilla_ventas']['encargada'] = 'ARIANE';   // misma persona

        $r = (new EvaluadorExtraccion())->evaluar(self::ESPERADO, $leido);

        $this->assertSame(6, $r['campos_ok']);
        $this->assertSame(['planilla_ventas.qr: esperado -26, leído 26'], $r['errores']);
    }

    public function test_documento_no_detectado_cuenta_todos_sus_campos_como_error(): void
    {
        $r = (new EvaluadorExtraccion())->evaluar(self::ESPERADO, [['tipo' => 'pago_chicas', 'pago_chicas' => []]]);

        $this->assertSame(0, $r['tipos_encontrados']);
        $this->assertSame(7, $r['campos']);
        $this->assertSame(0, $r['campos_ok']);
        $this->assertStringContainsString('la IA leyó: pago_chicas', $r['errores'][0]);
    }

    public function test_ilegible_no_es_cero(): void
    {
        $leido = self::ESPERADO;
        $leido[0]['planilla_ventas']['total_ingresos'] = null;

        $r = (new EvaluadorExtraccion())->evaluar(self::ESPERADO, $leido);

        $this->assertSame(6, $r['campos_ok']);
    }
}
