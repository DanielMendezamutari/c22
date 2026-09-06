<?php

namespace App\Application\UseCases\Transformacion;

use App\Domain\Ports\MovimientoRepositoryPort;
use App\Domain\Ports\RecetaRepositoryPort;
use App\Domain\Ports\TurnoRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarBajaUseCase
{
    private TurnoRepositoryPort $turnoRepo;
    private MovimientoRepositoryPort $movimientoRepo;
    private RecetaRepositoryPort $recetaRepo;

    public function __construct(
        TurnoRepositoryPort $turnoRepo,
        MovimientoRepositoryPort $movimientoRepo,
        RecetaRepositoryPort $recetaRepo
    ) {
        $this->turnoRepo = $turnoRepo;
        $this->movimientoRepo = $movimientoRepo;
        $this->recetaRepo = $recetaRepo;
    }

    public function ejecutar(array $comando): array
    {
        $uuidLocal = $comando['uuid_local'] ?? null;
        if (!$uuidLocal) {
            throw new InvalidArgumentException("El UUID es requerido.");
        }

        if ($this->movimientoRepo->existeUuid($uuidLocal)) {
            return [
                'idempotente' => true,
                'mensaje' => 'La baja ya había sido registrada.',
                'uuid_local' => $uuidLocal,
            ];
        }

        return DB::transaction(function () use ($comando, $uuidLocal) {
            $turnoId = (int) $comando['turno_id'];
            $productoId = (int) $comando['producto_id'];
            $cantidad = (float) $comando['cantidad'];
            $fotoPath = $comando['foto_path'] ?? null;
            $observaciones = $comando['observaciones'] ?? 'Baja / Rotura de producto';

            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno || $turno->estado !== 'abierto') {
                throw new DomainException("El turno {$turnoId} no está disponible para registrar bajas.");
            }

            // 1. Asentar movimiento de baja
            $mov = $this->movimientoRepo->registrarMovimiento([
                'uuid_local' => $uuidLocal,
                'turno_id' => $turnoId,
                'sucursal_id' => $turno->sucursal_id,
                'producto_id' => $productoId,
                'tipo_movimiento' => 'baja_rotura',
                'cantidad' => $cantidad,
                'foto_path' => $fotoPath,
                'observaciones' => $observaciones,
                'fecha_movimiento' => now(),
            ]);

            // 2. Si el producto es producto terminado de una receta de transformación,
            // descontar la comisión que se hubiere asignado
            $recetas = $this->recetaRepo->listarRecetasTransformacion();
            $recetaAsociada = collect($recetas)->firstWhere('producto_destino_id', $productoId);

            $montoReversion = 0.00;
            if ($recetaAsociada) {
                $tarifa = (float) $recetaAsociada['tarifa_comision_unidad'];
                $montoReversion = round($cantidad * $tarifa, 2);

                $nuevaBruta = max(0.00, round($turno->total_comision_bruta - $montoReversion, 2));
                $nuevasNetas = max(0, $turno->total_transformaciones_netas - (int) $cantidad);

                $this->turnoRepo->actualizar($turnoId, [
                    'total_comision_bruta' => $nuevaBruta,
                    'total_transformaciones_netas' => $nuevasNetas,
                ]);
            }

            return [
                'idempotente' => false,
                'movimiento_id' => $mov->id,
                'producto_id' => $productoId,
                'cantidad_baja' => $cantidad,
                'comision_revertida_bs' => $montoReversion,
                'total_turno_comision_bruta' => $recetaAsociada ? $nuevaBruta : $turno->total_comision_bruta,
            ];
        });
    }
}
