<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\ParserTextoCierre;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTextoCierreTest extends TestCase
{
    public function test_interpreta_el_ejemplo_real_exacto(): void
    {
        $texto = "Casa 22vip\nVenta total:1520\nQr:113\nLink:0\nEfectivo:90\nFanny:169";

        $this->assertSame([
            'casa' => 'Casa 22vip',
            'venta_total' => 1520.0,
            'qr' => 113.0,
            'linkser' => 0.0,
            'efectivo' => 90.0,
            'pagos_personal' => ['Fanny' => 169.0],
            'lineas_no_reconocidas' => [],
        ], (new ParserTextoCierre)->parsear($texto));
    }

    #[DataProvider('formatosNumericos')]
    public function test_interpreta_formatos_numericos(string $numero, float $esperado): void
    {
        $resultado = (new ParserTextoCierre)->parsear('Venta total: '.$numero);

        $this->assertSame($esperado, $resultado['venta_total']);
        $this->assertNull($resultado['casa']);
        $this->assertSame([], $resultado['lineas_no_reconocidas']);
    }

    public static function formatosNumericos(): iterable
    {
        yield ['1.520', 1520.0];
        yield ['1,520.50', 1520.5];
        yield ['1520,50', 1520.5];
        yield ['1.520,50', 1520.5];
        yield ['Bs 1520', 1520.0];
        yield ['1520 bs', 1520.0];
        yield ['1520.-', 1520.0];
        yield ['1520.50', 1520.5];
        yield ['1,520', 1520.0];
        yield ['1.520.000', 1520000.0];
        yield ['1,520,000.50', 1520000.5];
        yield ['1.520.000,50', 1520000.5];
        yield ['1520,5', 1520.5];
        yield ['1520.5', 1520.5];
        yield ['0', 0.0];
        yield ['-1520', 1520.0];
        yield ['- Bs 1.520,50', 1520.5];
        yield ['Bs -1520', 1520.0];
        yield ['−90,50', 90.5];
    }

    #[DataProvider('sinonimos')]
    public function test_reconoce_sinonimos_con_todos_los_separadores(string $nombre, string $clave): void
    {
        foreach ([':', '=', ' '] as $separador) {
            $resultado = (new ParserTextoCierre)->parsear($nombre.$separador.'Bs 42,50');

            $this->assertSame(42.5, $resultado[$clave], $nombre.$separador);
            $this->assertNull($resultado['casa']);
            $this->assertSame([], $resultado['pagos_personal']);
        }
    }

    public static function sinonimos(): iterable
    {
        foreach ([
            'venta_total' => ['venta total', 'ventas', 'total venta', 'venta'],
            'qr' => ['qr', 'q.r.', 'transferencia'],
            'linkser' => ['link', 'linkser', 'tarjeta'],
            'efectivo' => ['efectivo', 'sobre', 'efectivo en sobre'],
        ] as $clave => $nombres) {
            foreach ($nombres as $nombre) {
                yield $nombre => [$nombre, $clave];
            }
        }
    }

    public function test_ignora_mayusculas_tildes_y_espacios_en_claves(): void
    {
        $resultado = (new ParserTextoCierre)->parsear(
            "  Casa 22vip  \r\n VÉNTA   TÓTAL = 100 \r\n Q . R . : 20\r\n TÁRJÉTA 30\r\n EFÉCTIVO   EN SÓBRE : 50\r\n"
        );

        $this->assertSame('Casa 22vip', $resultado['casa']);
        $this->assertSame(100.0, $resultado['venta_total']);
        $this->assertSame(20.0, $resultado['qr']);
        $this->assertSame(30.0, $resultado['linkser']);
        $this->assertSame(50.0, $resultado['efectivo']);
        $this->assertSame([], $resultado['lineas_no_reconocidas']);
    }

    public function test_deja_claves_faltantes_en_null_y_colecciones_vacias(): void
    {
        $this->assertSame([
            'casa' => null,
            'venta_total' => null,
            'qr' => null,
            'linkser' => null,
            'efectivo' => null,
            'pagos_personal' => [],
            'lineas_no_reconocidas' => [],
        ], (new ParserTextoCierre)->parsear(" \n\r\n"));

        $resultado = (new ParserTextoCierre)->parsear('QR:0');
        $this->assertSame(0.0, $resultado['qr']);
        foreach (['casa', 'venta_total', 'linkser', 'efectivo'] as $clave) {
            $this->assertNull($resultado[$clave]);
        }
    }

    public function test_conserva_nombres_de_varias_personas_y_sus_montos(): void
    {
        $resultado = (new ParserTextoCierre)->parsear(" Fanny :169\n  María José  : Bs 90,50\nJOSÉ = -25");

        $this->assertSame(['Fanny' => 169.0, 'María José' => 90.5, 'JOSÉ' => 25.0], $resultado['pagos_personal']);
        $this->assertNull($resultado['casa']);
    }

    public function test_guarda_lineas_no_reconocidas_sin_inventar_montos(): void
    {
        $resultado = (new ParserTextoCierre)->parsear(
            "Casa 22vip\nPendiente de confirmar\nQR: sin dato\nVenta: 12,34,56\nFanny: 20 pendiente\nLink:0"
        );

        $this->assertSame([
            'Pendiente de confirmar', 'QR: sin dato', 'Venta: 12,34,56', 'Fanny: 20 pendiente',
        ], $resultado['lineas_no_reconocidas']);
        $this->assertNull($resultado['qr']);
        $this->assertNull($resultado['venta_total']);
        $this->assertSame([], $resultado['pagos_personal']);
        $this->assertSame(0.0, $resultado['linkser']);
    }

    public function test_identifica_la_primera_linea_no_numerica_como_casa(): void
    {
        $resultado = (new ParserTextoCierre)->parsear("QR:113\nCasa 22\nOtra observación");

        $this->assertSame('Casa 22', $resultado['casa']);
        $this->assertSame(['Otra observación'], $resultado['lineas_no_reconocidas']);
    }
}
