<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Turno;

interface TurnoRepositoryPort
{
    public function buscarPorId(int $id): ?Turno;
    public function buscarTurnoActivoPorBarman(int $barmanId, int $sucursalId): ?Turno;
    public function crear(array $datos): Turno;
    public function actualizar(int $id, array $datos): bool;
    public function asentarCorte(int $turnoId, int $productoId, string $tipoCorte, float $cantidad): void;
    public function obtenerCortes(int $turnoId, ?string $tipoCorte = null): array;
}
