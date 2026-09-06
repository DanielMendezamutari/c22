<?php

namespace App\Domain\Ports;

use App\Infrastructure\Persistence\Eloquent\Models\RecetaTransformacion;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaCombo;

interface RecetaRepositoryPort
{
    public function obtenerRecetaActiva(int $insumoOrigenId, int $productoDestinoId): ?RecetaTransformacion;
    public function listarRecetasTransformacion(): array;
    public function crearRecetaTransformacion(array $datos): RecetaTransformacion;
    public function actualizarRecetaTransformacion(int $id, array $datos): bool;
    
    public function listarRecetasCombos(): array;
    public function crearRecetaCombo(array $datos): RecetaCombo;
    public function buscarComboPorId(int $comboId): ?RecetaCombo;
}
