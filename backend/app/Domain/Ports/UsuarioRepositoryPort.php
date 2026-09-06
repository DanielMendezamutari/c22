<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\Usuario;

interface UsuarioRepositoryPort
{
    public function buscarPorId(int $id): ?Usuario;
    public function buscarPorPin(string $pin, ?int $sucursalId = null): ?Usuario;
    public function actualizarSaldoDeudor(int $usuarioId, float $montoVariacion): bool;
    public function actualizarSucursalRotacion(int $usuarioId, int $sucursalId): bool;
}
