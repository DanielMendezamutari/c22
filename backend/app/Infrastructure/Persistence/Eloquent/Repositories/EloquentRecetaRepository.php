<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\RecetaRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaTransformacion;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaCombo;

class EloquentRecetaRepository implements RecetaRepositoryPort
{
    public function obtenerRecetaActiva(int $insumoOrigenId, int $productoDestinoId): ?RecetaTransformacion
    {
        return RecetaTransformacion::where('insumo_origen_id', $insumoOrigenId)
            ->where('producto_destino_id', $productoDestinoId)
            ->where('activo', true)
            ->first();
    }

    public function listarRecetasTransformacion(): array
    {
        return RecetaTransformacion::with(['insumoOrigen', 'productoDestino'])
            ->get()
            ->toArray();
    }

    public function crearRecetaTransformacion(array $datos): RecetaTransformacion
    {
        return RecetaTransformacion::create($datos);
    }

    public function actualizarRecetaTransformacion(int $id, array $datos): bool
    {
        $receta = RecetaTransformacion::findOrFail($id);
        return $receta->update($datos);
    }

    public function listarRecetasCombos(): array
    {
        return RecetaCombo::with('productoTerminado')
            ->get()
            ->toArray();
    }

    public function crearRecetaCombo(array $datos): RecetaCombo
    {
        return RecetaCombo::create($datos);
    }

    public function buscarComboPorId(int $comboId): ?RecetaCombo
    {
        return RecetaCombo::find($comboId);
    }
}
