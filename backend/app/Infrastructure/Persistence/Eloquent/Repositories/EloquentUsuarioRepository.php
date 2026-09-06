<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\UsuarioRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class EloquentUsuarioRepository implements UsuarioRepositoryPort
{
    public function buscarPorId(int $id): ?Usuario
    {
        return Usuario::with('sucursalActual')->find($id);
    }

    public function buscarPorPin(string $pin, ?int $sucursalId = null): ?Usuario
    {
        $query = Usuario::where('activo', true);
        if ($sucursalId) {
            $query->where(function ($q) use ($sucursalId) {
                $q->where('sucursal_actual_id', $sucursalId)
                  ->orWhere('rol', 'admin'); // Admins pueden acceder en cualquier sucursal
            });
        }

        $usuarios = $query->get();

        foreach ($usuarios as $usuario) {
            if (Hash::check($pin, $usuario->pin_hash)) {
                return $usuario;
            }
        }

        return null;
    }

    public function actualizarSaldoDeudor(int $usuarioId, float $montoVariacion): bool
    {
        $usuario = Usuario::findOrFail($usuarioId);
        $nuevoSaldo = max(0.00, round($usuario->saldo_deudor_acumulado + $montoVariacion, 2));
        return $usuario->update(['saldo_deudor_acumulado' => $nuevoSaldo]);
    }

    public function actualizarSucursalRotacion(int $usuarioId, int $sucursalId): bool
    {
        $usuario = Usuario::findOrFail($usuarioId);
        return $usuario->update(['sucursal_actual_id' => $sucursalId]);
    }
}
