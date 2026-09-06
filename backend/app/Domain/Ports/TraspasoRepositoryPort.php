<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Traspaso;

interface TraspasoRepositoryPort
{
    public function crear(array $datos): Traspaso;
    public function buscarPorId(int $id): ?Traspaso;
    public function recibir(
        int $id,
        float $cantidadRecibidaConforme,
        float $cantidadMermaTransito,
        int $usuarioReceptorId,
        ?string $fotoRecepcion = null
    ): bool;
    public function obtenerTraspasosNetosPorTurno(int $sucursalId, int $productoId, string $desde, string $hasta): float;
    public function listarPendientes(int $sucursalDestinoId): array;
}
