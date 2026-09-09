<?php

namespace App\Application\UseCases\Transformacion;

use App\Domain\Entities\TransaccionRelleno;
use App\Domain\Ports\MovimientoRepositoryPort;
use App\Domain\Ports\RecetaRepositoryPort;
use App\Domain\Ports\TurnoRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarTransformacionUseCase
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
            throw new InvalidArgumentException("El identificador UUID local es obligatorio para garantizar idempotencia.");
        }

        // 1. Validar idempotencia offline
        if ($this->movimientoRepo->existeUuid($uuidLocal)) {
            return [
                'idempotente' => true,
                'mensaje' => 'La transacción ya había sido registrada previamente.',
                'uuid_local' => $uuidLocal,
            ];
        }

        return DB::transaction(function () use ($comando, $uuidLocal) {
            $turnoId = (int) $comando['turno_id'];
            $recetaId = (int) $comando['receta_id'];
            $insumosOrigenCompuestos = $comando['insumos_origen'] ?? null;

            if (!empty($insumosOrigenCompuestos) && is_array($insumosOrigenCompuestos)) {
                $cantidadInsumo = (float) array_sum(array_column($insumosOrigenCompuestos, 'cantidad'));
            } else {
                $cantidadInsumo = (float) ($comando['cantidad_insumo'] ?? 1.0);
            }

            $cantidadProducida = (int) $comando['cantidad_producida'];
            $cantidadRoturas = (int) ($comando['cantidad_roturas'] ?? 0);
            $observaciones = $comando['observaciones'] ?? null;

            // 2. Verificar estado del turno
            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno) {
                throw new DomainException("El turno {$turnoId} no existe.");
            }
            if ($turno->estado !== 'abierto') {
                throw new DomainException("No se pueden registrar transformaciones en un turno con estado '{$turno->estado}'.");
            }

            // Soporte de Rellenos en Cero (0 unidades producidas): jornada sin transformaciones
            if ($cantidadProducida === 0) {
                return [
                    'idempotente' => false,
                    'unidades_netas' => 0,
                    'comision_ganada_bs' => 0.00,
                    'total_turno_netas' => (int) $turno->total_transformaciones_netas,
                    'total_turno_comision_bruta' => (float) $turno->total_comision_bruta,
                    'alerta_ratio' => false,
                    'mensaje' => 'Registro de 0 transformaciones completado sin comisiones.',
                ];
            }

            // 3. Obtener receta de transformación
            $recetas = $this->recetaRepo->listarRecetasTransformacion();
            $receta = collect($recetas)->firstWhere('id', $recetaId);
            if (!$receta) {
                throw new DomainException("La receta de transformación {$recetaId} no fue encontrada.");
            }

            $insumoOrigenId = (int) ($comando['insumo_origen_id'] ?? $receta['insumo_origen_id']);

            // 3.1 Validar Stock Disponible en el Turno (Principio Constitucional III y Ecuación de Balance)
            $calcularStockDisponible = function (int $prodId) use ($turnoId): float {
                $stockInicial = (float) (\App\Infrastructure\Persistence\Eloquent\Models\CorteInventario::where('turno_id', $turnoId)
                    ->where('producto_id', $prodId)
                    ->whereIn('tipo_corte', ['inicial', 'apertura'])
                    ->value('cantidad') ?? 0.0);

                $ingresos = (float) \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::where('turno_id', $turnoId)
                    ->where('producto_id', $prodId)
                    ->whereIn('tipo_movimiento', ['ingreso', 'ingreso_compra', 'traspaso_entrada'])
                    ->sum('cantidad');

                $bajas = (float) \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::where('turno_id', $turnoId)
                    ->where('producto_id', $prodId)
                    ->where('tipo_movimiento', 'baja_rotura')
                    ->sum('cantidad');

                $consumosPrevios = (float) \App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario::where('turno_id', $turnoId)
                    ->where('producto_id', $prodId)
                    ->where('tipo_movimiento', 'transformacion_consumo')
                    ->sum('cantidad');

                return round($stockInicial + $ingresos - $bajas - $consumosPrevios, 2);
            };

            if (!empty($insumosOrigenCompuestos) && is_array($insumosOrigenCompuestos)) {
                foreach ($insumosOrigenCompuestos as $ingrediente) {
                    $ingId = (int) $ingrediente['insumo_id'];
                    $ingCant = (float) $ingrediente['cantidad'];
                    $disp = $calcularStockDisponible($ingId);
                    if ($ingCant > $disp) {
                        $prod = \App\Infrastructure\Persistence\Eloquent\Models\Producto::find($ingId);
                        $nombre = $prod ? $prod->nombre : "Insumo #{$ingId}";
                        throw new DomainException("Stock insuficiente de '{$nombre}'. Disponible en tu turno: {$disp}, requerido: {$ingCant}.");
                    }
                }
            } else {
                $disp = $calcularStockDisponible($insumoOrigenId);
                if ($cantidadInsumo > $disp) {
                    $prod = \App\Infrastructure\Persistence\Eloquent\Models\Producto::find($insumoOrigenId);
                    $nombre = $prod ? $prod->nombre : "Insumo #{$insumoOrigenId}";
                    throw new DomainException("Stock insuficiente de '{$nombre}'. Disponible en tu turno: {$disp}, requerido para este relleno: {$cantidadInsumo}. Registra primero la recepción en 'NUEVO INGRESO' si llegaron más unidades.");
                }
            }

            $tarifa = (float) $receta['tarifa_comision_unidad'];
            $ratioEsperado = (float) $receta['ratio_referencia_esperado'];
            $toleranciaMax = (float) $receta['umbral_desviacion_alerta'];

            // 4. Instanciar Entidad de Dominio Puro para validar reglas y calcular
            $transaccion = new TransaccionRelleno(
                $uuidLocal,
                $turnoId,
                $turno->sucursal_id,
                $insumoOrigenId,
                $cantidadInsumo,
                (int) $receta['producto_destino_id'],
                $cantidadProducida,
                $cantidadRoturas,
                $tarifa
            );

            $unidadesNetas = $transaccion->unidadesNetas();
            $comisionDevengada = $transaccion->comisionGenerada();
            $ratio = $transaccion->ratioConversion();
            $excedeTolerancia = $ratio->excedeTolerancia($ratioEsperado, $toleranciaMax);

            $fechaMovimiento = now();

            // 5. Asentar movimiento(s) de consumo de materia prima
            if (!empty($insumosOrigenCompuestos) && is_array($insumosOrigenCompuestos)) {
                // Caso Compuesto (ej. Blackstone + Chancellor)
                foreach ($insumosOrigenCompuestos as $idx => $ingrediente) {
                    $this->movimientoRepo->registrarMovimiento([
                        'uuid_local' => $uuidLocal . "-consumo-comp-{$idx}",
                        'turno_id' => $turnoId,
                        'sucursal_id' => $turno->sucursal_id,
                        'producto_id' => (int) $ingrediente['insumo_id'],
                        'tipo_movimiento' => 'transformacion_consumo',
                        'cantidad' => (float) $ingrediente['cantidad'],
                        'receta_id' => $recetaId,
                        'ratio_calculado' => $ratio->ratio(),
                        'observaciones' => $observaciones ? "Compuesto: {$observaciones}" : 'Transformación compuesta',
                        'fecha_movimiento' => $fechaMovimiento,
                    ]);
                }
            } else {
                // Caso Simple / Alternativo (ej. Corona con Moema, Paceña u Orureña)
                $this->movimientoRepo->registrarMovimiento([
                    'uuid_local' => $uuidLocal . '-consumo',
                    'turno_id' => $turnoId,
                    'sucursal_id' => $turno->sucursal_id,
                    'producto_id' => $insumoOrigenId,
                    'tipo_movimiento' => 'transformacion_consumo',
                    'cantidad' => $transaccion->cantidadInsumoConsumida(),
                    'receta_id' => $recetaId,
                    'ratio_calculado' => $ratio->ratio(),
                    'observaciones' => $observaciones,
                    'fecha_movimiento' => $fechaMovimiento,
                ]);
            }

            // 6. Asentar movimiento de producción terminada
            $movProduccion = $this->movimientoRepo->registrarMovimiento([
                'uuid_local' => $uuidLocal,
                'turno_id' => $turnoId,
                'sucursal_id' => $turno->sucursal_id,
                'producto_id' => $transaccion->productoDestinoId(),
                'tipo_movimiento' => 'transformacion_produccion',
                'cantidad' => $transaccion->cantidadDestinoProducida(),
                'receta_id' => $recetaId,
                'ratio_calculado' => $ratio->ratio(),
                'observaciones' => $observaciones,
                'fecha_movimiento' => $fechaMovimiento,
            ]);

            // 7. Si hubo roturas, asentar movimiento de baja/rotura
            if ($transaccion->cantidadRoturas() > 0) {
                $this->movimientoRepo->registrarMovimiento([
                    'uuid_local' => $uuidLocal . '-baja',
                    'turno_id' => $turnoId,
                    'sucursal_id' => $turno->sucursal_id,
                    'producto_id' => $transaccion->productoDestinoId(),
                    'tipo_movimiento' => 'baja_rotura',
                    'cantidad' => $transaccion->cantidadRoturas(),
                    'receta_id' => $recetaId,
                    'ratio_calculado' => null,
                    'observaciones' => 'Rotura/merma durante transformación',
                    'fecha_movimiento' => $fechaMovimiento,
                ]);
            }

            // 8. Actualizar acumulados del turno
            $nuevoTotalNetas = $turno->total_transformaciones_netas + $unidadesNetas;
            $nuevoTotalBruta = round($turno->total_comision_bruta + $comisionDevengada, 2);

            $this->turnoRepo->actualizar($turnoId, [
                'total_transformaciones_netas' => $nuevoTotalNetas,
                'total_comision_bruta' => $nuevoTotalBruta,
            ]);

            return [
                'idempotente' => false,
                'movimiento_id' => $movProduccion->id,
                'turno_id' => $turnoId,
                'insumo_consumido' => $transaccion->cantidadInsumoConsumida(),
                'producto_terminado' => $transaccion->cantidadDestinoProducida(),
                'roturas_descontadas' => $transaccion->cantidadRoturas(),
                'unidades_netas' => $unidadesNetas,
                'tarifa_unidad_bs' => $tarifa,
                'comision_generada_bs' => $comisionDevengada,
                'ratio_calculado' => $ratio->ratio(),
                'alerta_merma' => $excedeTolerancia,
                'total_turno_comision_bruta' => $nuevoTotalBruta,
            ];
        });
    }
}
