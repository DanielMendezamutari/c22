<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Entidad Pura de Dominio para equivalencia de combos a unidades físicas.
 */
class RecetaCombo
{
    private ?int $id;
    private string $nombreCombo;
    private int $productoTerminadoId;
    private int $unidadesEquivalentes;
    private bool $activo;

    public function __construct(
        ?int $id,
        string $nombreCombo,
        int $productoTerminadoId,
        int $unidadesEquivalentes,
        bool $activo = true
    ) {
        if ($unidadesEquivalentes <= 0) {
            throw new InvalidArgumentException("Las unidades equivalentes del combo deben ser mayores a cero.");
        }

        $this->id = $id;
        $this->nombreCombo = $nombreCombo;
        $this->productoTerminadoId = $productoTerminadoId;
        $this->unidadesEquivalentes = $unidadesEquivalentes;
        $this->activo = $activo;
    }

    public function id(): ?int { return $this->id; }
    public function nombreCombo(): string { return $this->nombreCombo; }
    public function productoTerminadoId(): int { return $this->productoTerminadoId; }
    public function unidadesEquivalentes(): int { return $this->unidadesEquivalentes; }
    public function activo(): bool { return $this->activo; }

    /**
     * Convierte una cantidad de combos vendidos a unidades físicas correspondientes.
     * Ejemplo: 5 baldes de 6 -> 30 unidades.
     */
    public function desglosarAUnidades(int $cantidadCombos): int
    {
        return $cantidadCombos * $this->unidadesEquivalentes;
    }
}
