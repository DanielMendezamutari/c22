<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;

interface MovimientoRepositoryPort
{
    public function registrarMovimiento(array $datos): MovimientoInventario;
    public function existeUuid(string $uuid): bool;
    public function obtenerPorTurno(int $turnoId): array;
    public function obtenerConsumoMateriaPrima(int $turnoId, int $productoId): float;
    public function obtenerProduccionTerminada(int $turnoId, int $productoId): float;
    public function obtenerBajasRoturas(int $turnoId, int $productoId): float;
    public function obtenerIngresos(int $turnoId, int $productoId): float;
}
