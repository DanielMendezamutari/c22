<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\TurnoRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\CorteInventario;

class EloquentTurnoRepository implements TurnoRepositoryPort
{
    public function buscarPorId(int $id): ?Turno
    {
        return Turno::with(['sucursal', 'barman', 'cortes.producto'])->find($id);
    }

    public function buscarTurnoActivoPorBarman(int $barmanId, int $sucursalId): ?Turno
    {
        return Turno::where('barman_id', $barmanId)
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado', ['abierto', 'cobrado'])
            ->latest('fecha_apertura')
            ->first();
    }

    public function crear(array $datos): Turno
    {
        return Turno::create($datos);
    }

    public function actualizar(int $id, array $datos): bool
    {
        $turno = Turno::findOrFail($id);
        return $turno->update($datos);
    }

    public function asentarCorte(int $turnoId, int $productoId, string $tipoCorte, float $cantidad): void
    {
        $tipoNormalizado = match (strtolower($tipoCorte)) {
            'apertura' => 'inicial',
            'cierre' => 'final',
            default => $tipoCorte,
        };

        CorteInventario::create([
            'turno_id' => $turnoId,
            'producto_id' => $productoId,
            'tipo_corte' => $tipoNormalizado,
            'cantidad' => $cantidad,
            'created_at' => now(),
        ]);
    }

    public function obtenerCortes(int $turnoId, ?string $tipoCorte = null): array
    {
        $query = CorteInventario::where('turno_id', $turnoId);
        if ($tipoCorte) {
            $tipoNormalizado = match (strtolower($tipoCorte)) {
                'apertura' => 'inicial',
                'cierre' => 'final',
                default => $tipoCorte,
            };
            $query->where(function ($q) use ($tipoCorte, $tipoNormalizado) {
                $q->where('tipo_corte', $tipoNormalizado)
                  ->orWhere('tipo_corte', $tipoCorte);
            });
        }
        return $query->with('producto')->get()->toArray();
    }
}
