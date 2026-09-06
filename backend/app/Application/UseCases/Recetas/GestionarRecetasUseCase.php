<?php

namespace App\Application\UseCases\Recetas;

use App\Domain\Ports\RecetaRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaCombo;
use App\Infrastructure\Persistence\Eloquent\Models\RecetaTransformacion;
use DomainException;

class GestionarRecetasUseCase
{
    private RecetaRepositoryPort $recetaRepo;

    public function __construct(RecetaRepositoryPort $recetaRepo)
    {
        $this->recetaRepo = $recetaRepo;
    }

    public function listarTransformaciones(): array
    {
        return $this->recetaRepo->listarRecetasTransformacion();
    }

    public function guardarTransformacion(array $datos, ?int $id = null): RecetaTransformacion
    {
        $insumoId = (int) $datos['insumo_origen_id'];
        $secundarioId = !empty($datos['insumo_secundario_id']) ? (int) $datos['insumo_secundario_id'] : null;
        $destinoId = (int) $datos['producto_destino_id'];
        $nombre = !empty($datos['nombre']) ? trim($datos['nombre']) : null;
        $tarifa = (float) ($datos['tarifa_comision_unidad'] ?? $datos['tarifa_comision'] ?? 1.00);
        $ratio = (float) ($datos['ratio_referencia_esperado'] ?? $datos['ratio_teorico'] ?? 1.00);
        $activo = (bool) ($datos['activo'] ?? true);

        if ($tarifa < 0) {
            throw new DomainException("La tarifa de comisión no puede ser negativa.");
        }

        if ($ratio <= 0) {
            throw new DomainException("El ratio teórico debe ser mayor a 0.");
        }

        $payload = [
            'nombre' => $nombre,
            'insumo_origen_id' => $insumoId,
            'insumo_secundario_id' => $secundarioId,
            'producto_destino_id' => $destinoId,
            'tarifa_comision_unidad' => $tarifa,
            'ratio_referencia_esperado' => $ratio,
            'activo' => $activo,
        ];

        if ($id) {
            $this->recetaRepo->actualizarRecetaTransformacion($id, $payload);
            return RecetaTransformacion::with(['insumoOrigen', 'insumoSecundario', 'productoDestino'])->findOrFail($id);
        }

        $receta = $this->recetaRepo->crearRecetaTransformacion($payload);
        return RecetaTransformacion::with(['insumoOrigen', 'insumoSecundario', 'productoDestino'])->findOrFail($receta->id);
    }

    public function eliminarTransformacion(int $id): bool
    {
        $receta = RecetaTransformacion::findOrFail($id);
        return $receta->delete();
    }

    public function listarCombos(): array
    {
        return $this->recetaRepo->listarRecetasCombos();
    }

    public function guardarCombo(array $datos, ?int $id = null): RecetaCombo
    {
        $nombre = trim($datos['nombre_combo']);
        $productoId = (int) $datos['producto_terminado_id'];
        $unidades = (int) ($datos['unidades_equivalentes'] ?? $datos['unidades_producto'] ?? 6);
        $activo = (bool) ($datos['activo'] ?? true);

        if ($unidades <= 0) {
            throw new DomainException("Un combo debe desglosar al menos 1 unidad de producto.");
        }

        if ($id) {
            $combo = RecetaCombo::findOrFail($id);
            $combo->update([
                'nombre_combo' => $nombre,
                'producto_terminado_id' => $productoId,
                'unidades_equivalentes' => $unidades,
                'activo' => $activo,
            ]);
            return $combo;
        }

        return $this->recetaRepo->crearRecetaCombo([
            'nombre_combo' => $nombre,
            'producto_terminado_id' => $productoId,
            'unidades_equivalentes' => $unidades,
            'activo' => $activo,
        ]);
    }

    public function eliminarCombo(int $id): bool
    {
        $combo = RecetaCombo::findOrFail($id);
        return $combo->delete();
    }
}
