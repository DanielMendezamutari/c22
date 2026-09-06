<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\MovimientoRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;

class EloquentMovimientoRepository implements MovimientoRepositoryPort
{
    public function registrarMovimiento(array $datos): MovimientoInventario
    {
        return MovimientoInventario::create($datos);
    }

    public function existeUuid(string $uuid): bool
    {
        return MovimientoInventario::where('uuid_local', $uuid)->exists();
    }

    public function obtenerPorTurno(int $turnoId): array
    {
        return MovimientoInventario::where('turno_id', $turnoId)
            ->with(['producto', 'receta'])
            ->orderBy('fecha_movimiento', 'asc')
            ->get()
            ->toArray();
    }

    public function obtenerConsumoMateriaPrima(int $turnoId, int $productoId): float
    {
        return (float) MovimientoInventario::where('turno_id', $turnoId)
            ->where('producto_id', $productoId)
            ->where('tipo_movimiento', 'transformacion_consumo')
            ->sum('cantidad');
    }

    public function obtenerProduccionTerminada(int $turnoId, int $productoId): float
    {
        return (float) MovimientoInventario::where('turno_id', $turnoId)
            ->where('producto_id', $productoId)
            ->where('tipo_movimiento', 'transformacion_produccion')
            ->sum('cantidad');
    }

    public function obtenerBajasRoturas(int $turnoId, int $productoId): float
    {
        return (float) MovimientoInventario::where('turno_id', $turnoId)
            ->where('producto_id', $productoId)
            ->where('tipo_movimiento', 'baja_rotura')
            ->sum('cantidad');
    }

    public function obtenerIngresos(int $turnoId, int $productoId): float
    {
        return (float) MovimientoInventario::where('turno_id', $turnoId)
            ->where('producto_id', $productoId)
            ->where('tipo_movimiento', 'ingreso')
            ->sum('cantidad');
    }
}
