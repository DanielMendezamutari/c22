<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\TraspasoRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\Traspaso;

class EloquentTraspasoRepository implements TraspasoRepositoryPort
{
    public function crear(array $datos): Traspaso
    {
        return Traspaso::create($datos);
    }

    public function buscarPorId(int $id): ?Traspaso
    {
        return Traspaso::with(['sucursalOrigen', 'sucursalDestino', 'producto', 'usuarioEmisor', 'usuarioReceptor', 'detalles.producto'])
            ->find($id);
    }

    public function recibir(
        int $id,
        float $cantidadRecibidaConforme,
        float $cantidadMermaTransito,
        int $usuarioReceptorId,
        ?string $fotoRecepcion = null
    ): bool {
        $traspaso = Traspaso::findOrFail($id);
        $estado = ($cantidadMermaTransito > 0) ? 'recibido_con_discrepancia' : 'recibido_conforme';

        return $traspaso->update([
            'cantidad_recibida_conforme' => $cantidadRecibidaConforme,
            'cantidad_merma_transito' => $cantidadMermaTransito,
            'estado' => $estado,
            'usuario_receptor_id' => $usuarioReceptorId,
            'foto_recepcion' => $fotoRecepcion,
            'fecha_recepcion' => now(),
        ]);
    }

    public function obtenerTraspasosNetosPorTurno(int $sucursalId, int $productoId, string $desde, string $hasta): float
    {
        $entradas = (float) Traspaso::where('sucursal_destino_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->where('estado', '!=', 'cancelado')
            ->whereBetween('fecha_recepcion', [$desde, $hasta])
            ->sum('cantidad_recibida_conforme');

        $salidas = (float) Traspaso::where('sucursal_origen_id', $sucursalId)
            ->where('producto_id', $productoId)
            ->where('estado', '!=', 'cancelado')
            ->whereBetween('fecha_envio', [$desde, $hasta])
            ->sum('cantidad_despachada');

        return round($entradas - $salidas, 2);
    }

    public function listarPendientes(int $sucursalDestinoId): array
    {
        return Traspaso::where('sucursal_destino_id', $sucursalDestinoId)
            ->where('estado', 'en_transito')
            ->with(['sucursalOrigen', 'producto', 'usuarioEmisor', 'detalles.producto'])
            ->orderByDesc('fecha_envio')
            ->get()
            ->toArray();
    }
}
