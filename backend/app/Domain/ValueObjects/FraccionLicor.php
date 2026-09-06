<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object inmutable para representar cantidades de licores en múltiplos de cuarto (0.25).
 * Cumple con el Principio Constitucional III: Fracciones parametrizadas de 0.25, 0.50, 0.75 y unidades enteras.
 */
final class FraccionLicor
{
    private float $valor;

    public function __construct(float $valor)
    {
        if ($valor < 0) {
            throw new InvalidArgumentException("La fracción de licor no puede ser negativa: {$valor}");
        }

        // Redondear a 2 decimales para evitar imprecisiones de punto flotante
        $valorRedondeado = round($valor, 2);
        $decimal = round(fmod($valorRedondeado, 1.0), 2);

        // Los decimales válidos deben ser 0.00, 0.25, 0.50 o 0.75
        $decimalesValidos = [0.00, 0.25, 0.50, 0.75, 1.00];

        if (!in_array($decimal, $decimalesValidos, true)) {
            throw new InvalidArgumentException(
                "La cantidad {$valor} no es un múltiplo válido de cuarto de botella (0.25, 0.50, 0.75, 1.00)."
            );
        }

        $this->valor = $valorRedondeado;
    }

    public static function desdeDecimal(float $valor): self
    {
        return new self($valor);
    }

    public static function cero(): self
    {
        return new self(0.00);
    }

    public function valor(): float
    {
        return $this->valor;
    }

    public function enteros(): int
    {
        return (int) floor($this->valor);
    }

    public function fraccion(): float
    {
        return round(fmod($this->valor, 1.0), 2);
    }

    public function sumar(self $otra): self
    {
        return new self(round($this->valor + $otra->valor(), 2));
    }

    public function restar(self $otra): self
    {
        $resultado = round($this->valor - $otra->valor(), 2);
        if ($resultado < 0) {
            throw new InvalidArgumentException("El resultado de la resta de inventario no puede ser negativo: {$resultado}");
        }
        return new self($resultado);
    }

    public function esCero(): bool
    {
        return $this->valor === 0.00;
    }

    public function aTexto(): string
    {
        $enteros = $this->enteros();
        $fraccion = $this->fraccion();

        $fraccionTexto = match ($fraccion) {
            0.25 => '1/4',
            0.50 => '1/2',
            0.75 => '3/4',
            default => '',
        };

        if ($enteros > 0 && $fraccionTexto !== '') {
            return "{$enteros} {$fraccionTexto}";
        }

        if ($enteros === 0 && $fraccionTexto !== '') {
            return $fraccionTexto;
        }

        return (string) $enteros;
    }

    public function __toString(): string
    {
        return number_format($this->valor, 2, '.', '');
    }
}
