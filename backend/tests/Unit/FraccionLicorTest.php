<?php

namespace Tests\Unit;

use App\Domain\ValueObjects\FraccionLicor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FraccionLicorTest extends TestCase
{
    public function test_creacion_con_multiplos_validos(): void
    {
        $f1 = FraccionLicor::desdeDecimal(0.00);
        $this->assertEquals(0.00, $f1->valor());

        $f2 = FraccionLicor::desdeDecimal(0.25);
        $this->assertEquals(0.25, $f2->valor());
        $this->assertEquals('1/4', $f2->aTexto());

        $f3 = FraccionLicor::desdeDecimal(0.50);
        $this->assertEquals(0.50, $f3->valor());
        $this->assertEquals('1/2', $f3->aTexto());

        $f4 = FraccionLicor::desdeDecimal(0.75);
        $this->assertEquals(0.75, $f4->valor());
        $this->assertEquals('3/4', $f4->aTexto());

        $f5 = FraccionLicor::desdeDecimal(3.75);
        $this->assertEquals(3.75, $f5->valor());
        $this->assertEquals(3, $f5->enteros());
        $this->assertEquals(0.75, $f5->fraccion());
        $this->assertEquals('3 3/4', $f5->aTexto());
    }

    public function test_rechaza_fracciones_no_multiplos_de_cuarto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FraccionLicor::desdeDecimal(0.33);
    }

    public function test_rechaza_valores_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FraccionLicor::desdeDecimal(-0.25);
    }

    public function test_suma_y_resta_exacta(): void
    {
        $apertura = FraccionLicor::desdeDecimal(3.50);
        $cierre = FraccionLicor::desdeDecimal(2.75);

        $consumo = $apertura->restar($cierre);
        $this->assertEquals(0.75, $consumo->valor());
        $this->assertEquals('3/4', $consumo->aTexto());

        $suma = $cierre->sumar($consumo);
        $this->assertEquals(3.50, $suma->valor());
    }
}
