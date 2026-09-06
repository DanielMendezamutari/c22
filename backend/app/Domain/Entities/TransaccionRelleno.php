<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\RatioConversion;
use InvalidArgumentException;

/**
 * Entidad Pura de Dominio que representa la transacción atómica de transformación (Relleno).
 * Aplica Principio Constitucional II: 0% comisiones a no transformados o mermas/roturas.
 */
class TransaccionRelleno
{
    private string $uuidLocal;
    private int $turnoId;
    private int $sucursalId;
    private int $insumoOrigenId;
    private float $cantidadInsumoConsumida;
    private int $productoDestinoId;
    private int $cantidadDestinoProducida;
    private int $cantidadRoturas;
    private float $tarifaComisionPorUnidad;
    private RatioConversion $ratioConversion;

    public function __construct(
        string $uuidLocal,
        int $turnoId,
        int $sucursalId,
        int $insumoOrigenId,
        float $cantidadInsumoConsumida,
        int $productoDestinoId,
        int $cantidadDestinoProducida,
        int $cantidadRoturas,
        float $tarifaComisionPorUnidad
    ) {
        if ($cantidadInsumoConsumida <= 0) {
            throw new InvalidArgumentException("La cantidad de insumo consumido debe ser mayor a cero.");
        }
        if ($cantidadDestinoProducida <= 0) {
            throw new InvalidArgumentException("La cantidad de producto producido debe ser mayor a cero.");
        }
        if ($cantidadRoturas < 0) {
            throw new InvalidArgumentException("Las roturas no pueden ser negativas.");
        }
        if ($cantidadRoturas > $cantidadDestinoProducida) {
            throw new InvalidArgumentException("Las roturas no pueden superar la cantidad total producida.");
        }

        $this->uuidLocal = $uuidLocal;
        $this->turnoId = $turnoId;
        $this->sucursalId = $sucursalId;
        $this->insumoOrigenId = $insumoOrigenId;
        $this->cantidadInsumoConsumida = round($cantidadInsumoConsumida, 2);
        $this->productoDestinoId = $productoDestinoId;
        $this->cantidadDestinoProducida = $cantidadDestinoProducida;
        $this->cantidadRoturas = $cantidadRoturas;
        $this->tarifaComisionPorUnidad = round($tarifaComisionPorUnidad, 2);
        $this->ratioConversion = new RatioConversion($cantidadInsumoConsumida, $cantidadDestinoProducida);
    }

    public function uuidLocal(): string { return $this->uuidLocal; }
    public function turnoId(): int { return $this->turnoId; }
    public function sucursalId(): int { return $this->sucursalId; }
    public function insumoOrigenId(): int { return $this->insumoOrigenId; }
    public function cantidadInsumoConsumida(): float { return $this->cantidadInsumoConsumida; }
    public function productoDestinoId(): int { return $this->productoDestinoId; }
    public function cantidadDestinoProducida(): int { return $this->cantidadDestinoProducida; }
    public function cantidadRoturas(): int { return $this->cantidadRoturas; }
    public function tarifaComision(): float { return $this->tarifaComisionPorUnidad; }
    public function ratioConversion(): RatioConversion { return $this->ratioConversion; }

    /**
     * Unidades netas válidas para cobro de comisión: producidas menos roturas.
     */
    public function unidadesNetas(): int
    {
        return max(0, $this->cantidadDestinoProducida - $this->cantidadRoturas);
    }

    /**
     * Comisión total devengada en Bolivianos (Bs).
     */
    public function comisionGenerada(): float
    {
        return round($this->unidadesNetas() * $this->tarifaComisionPorUnidad, 2);
    }
}
