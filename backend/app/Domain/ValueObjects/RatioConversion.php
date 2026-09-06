<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object inmutable para representar el ratio de conversión empírico.
 * Cumple con el Principio Constitucional I: Descubrimiento empírico del rendimiento
 * (materia prima consumida / producto terminado logrado).
 */
final class RatioConversion
{
    private float $materiaPrimaConsumida;
    private float $productoTerminadoLogrado;
    private float $ratio;

    public function __construct(float $materiaPrimaConsumida, float $productoTerminadoLogrado)
    {
        if ($materiaPrimaConsumida < 0) {
            throw new InvalidArgumentException("La materia prima consumida no puede ser negativa.");
        }

        if ($productoTerminadoLogrado <= 0) {
            throw new InvalidArgumentException("El producto terminado logrado debe ser mayor a cero para calcular el ratio.");
        }

        $this->materiaPrimaConsumida = round($materiaPrimaConsumida, 2);
        $this->productoTerminadoLogrado = round($productoTerminadoLogrado, 2);
        $this->ratio = round($this->materiaPrimaConsumida / $this->productoTerminadoLogrado, 3);
    }

    public static function calcular(float $materiaPrimaConsumida, float $productoTerminadoLogrado): self
    {
        return new self($materiaPrimaConsumida, $productoTerminadoLogrado);
    }

    public function ratio(): float
    {
        return $this->ratio;
    }

    public function materiaPrima(): float
    {
        return $this->materiaPrimaConsumida;
    }

    public function productoTerminado(): float
    {
        return $this->productoTerminadoLogrado;
    }

    /**
     * Determina si el ratio excede el umbral de tolerancia porcentual respecto a un ratio esperado.
     * Ejemplo: esperado = 1.15, tolerancia = 15.0% -> max = 1.3225. Si ratio = 1.50, retorna true.
     */
    public function excedeTolerancia(float $ratioEsperado, float $porcentajeTolerancia): bool
    {
        if ($ratioEsperado <= 0) {
            return false;
        }

        $limiteMaximo = $ratioEsperado * (1 + ($porcentajeTolerancia / 100));
        return $this->ratio > round($limiteMaximo, 3);
    }

    /**
     * Calcula la desviación porcentual respecto a la norma esperada.
     */
    public function desviacionPorcentual(float $ratioEsperado): float
    {
        if ($ratioEsperado <= 0) {
            return 0.00;
        }

        return round((($this->ratio - $ratioEsperado) / $ratioEsperado) * 100, 2);
    }

    public function __toString(): string
    {
        return number_format($this->ratio, 3, '.', '');
    }
}
