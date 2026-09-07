<?php

namespace App\Application\UseCases\Inventario;

use App\Domain\Ports\TraspasoRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\CorteInventario;
use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;
use App\Infrastructure\Persistence\Eloquent\Models\Producto;
use App\Infrastructure\Persistence\Eloquent\Models\TraspasoDetalle;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnviarTraspasoUseCase
{
    private TraspasoRepositoryPort $traspasoRepo;

    public function __construct(TraspasoRepositoryPort $traspasoRepo)
    {
        $this->traspasoRepo = $traspasoRepo;
    }

    /**
     * Calcula el stock físico disponible en tiempo real en la sucursal de origen.
     */
    public static function obtenerStockDisponible(int $sucursalId, int $productoId, ?int $turnoId = null): float
    {
        // 1. Buscar turno activo si no se especificó
        $turno = $turnoId 
            ? Turno::find($turnoId) 
            : Turno::where('sucursal_id', $sucursalId)->where('estado', 'abierto')->latest()->first();

        $stockBase = 0.0;
        $fechaDesde = null;

        if ($turno) {
            // Conteo inicial del turno
            $corte = CorteInventario::where('turno_id', $turno->id)
                ->where('producto_id', $productoId)
                ->whereIn('tipo_corte', ['inicial', 'apertura'])
                ->first();

            if ($corte) {
                $stockBase = (float) $corte->cantidad + ((float) ($corte->fraccion_cuartos ?? 0.0));
            }
            $fechaDesde = $turno->fecha_apertura;
        } else {
            // Si no hay turno abierto, tomar el último corte registrado
            $ultimoCorte = CorteInventario::whereHas('turno', function ($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                })
                ->where('producto_id', $productoId)
                ->latest('id')
                ->first();

            if ($ultimoCorte) {
                $stockBase = (float) $ultimoCorte->cantidad + ((float) ($ultimoCorte->fraccion_cuartos ?? 0.0));
                $fechaDesde = $ultimoCorte->created_at;
            }
        }

        // 2. Sumar y restar movimientos ocurridos desde la apertura/corte
        $queryMovs = MovimientoInventario::where('sucursal_id', $sucursalId)
            ->where('producto_id', $productoId);

        if ($turno) {
            $queryMovs->where('turno_id', $turno->id);
        } elseif ($fechaDesde) {
            $queryMovs->where('fecha_movimiento', '>=', $fechaDesde);
        }

        $movs = $queryMovs->get();

        $entradas = 0.0;
        $salidas = 0.0;

        foreach ($movs as $m) {
            $cant = (float) $m->cantidad;
            switch ($m->tipo_movimiento) {
                case 'ingreso':
                case 'traspaso_entrada':
                case 'transformacion_produccion':
                case 'transformacion_destino':
                    $entradas += $cant;
                    break;
                case 'traspaso_salida':
                case 'baja_rotura':
                case 'baja':
                case 'transformacion_consumo':
                case 'transformacion_origen':
                    $salidas += $cant;
                    break;
            }
        }

        $stockFinal = $stockBase + $entradas - $salidas;
        return round(max(0.0, $stockFinal), 2);
    }

    public function ejecutar(array $comando): array
    {
        return DB::transaction(function () use ($comando) {
            $origenId = (int) $comando['sucursal_origen_id'];
            $destinoId = (int) $comando['sucursal_destino_id'];
            $emisorId = (int) $comando['usuario_emisor_id'];
            $observaciones = $comando['observaciones'] ?? 'Traspaso inter-sucursal';

            if ($origenId === $destinoId) {
                throw new DomainException("La sucursal de origen y destino no pueden ser la misma.");
            }

            // Buscar turno activo de la sucursal emisora
            $turnoOrigen = Turno::where('sucursal_id', $origenId)
                ->where('estado', 'abierto')
                ->latest()
                ->first();

            // Normalizar items a despachar
            $items = $comando['items'] ?? null;
            if (!empty($items) && is_array($items)) {
                $detallesData = [];
                $totalDespachado = 0;
                $primerProductoId = null;

                foreach ($items as $item) {
                    $pId = (int) $item['producto_id'];
                    $cant = (float) $item['cantidad'];
                    if ($cant <= 0) {
                        throw new DomainException("Cada producto debe tener una cantidad mayor a 0.");
                    }
                    if ($primerProductoId === null) {
                        $primerProductoId = $pId;
                    }
                    $totalDespachado += $cant;
                    $detallesData[] = [
                        'producto_id' => $pId,
                        'cantidad_despachada' => $cant,
                        'cantidad_recibida_conforme' => 0.00,
                        'cantidad_merma_transito' => 0.00,
                    ];
                }

                $productoId = $primerProductoId;
                $cantidad = $totalDespachado;
            } else {
                $productoId = (int) $comando['producto_id'];
                $cantidad = (float) $comando['cantidad'];

                if ($cantidad <= 0) {
                    throw new DomainException("La cantidad a traspasar debe ser mayor a 0.");
                }

                $detallesData = [
                    [
                        'producto_id' => $productoId,
                        'cantidad_despachada' => $cantidad,
                        'cantidad_recibida_conforme' => 0.00,
                        'cantidad_merma_transito' => 0.00,
                    ],
                ];
            }

            // VALIDACIÓN ESTRICTA DE STOCK FÍSICO EN ORIGEN
            foreach ($detallesData as $det) {
                $stockDisp = self::obtenerStockDisponible($origenId, $det['producto_id'], $turnoOrigen?->id);
                if ($det['cantidad_despachada'] > $stockDisp) {
                    $prod = Producto::find($det['producto_id']);
                    $pNombre = $prod ? $prod->nombre : "Producto #{$det['producto_id']}";
                    throw new DomainException("Stock insuficiente para despachar '{$pNombre}'. Saldo físico en barra: {$stockDisp}, solicitado: {$det['cantidad_despachada']}.");
                }
            }

            // Crear cabecera de traspaso
            $traspaso = $this->traspasoRepo->crear([
                'sucursal_origen_id' => $origenId,
                'sucursal_destino_id' => $destinoId,
                'producto_id' => $productoId,
                'cantidad_despachada' => $cantidad,
                'cantidad_recibida_conforme' => 0.00,
                'cantidad_merma_transito' => 0.00,
                'estado' => 'en_transito',
                'usuario_emisor_id' => $emisorId,
                'observaciones' => $observaciones,
                'fecha_envio' => now(),
            ]);

            // Crear detalles y asentar movimiento traspaso_salida
            foreach ($detallesData as $det) {
                TraspasoDetalle::create([
                    'traspaso_id' => $traspaso->id,
                    'producto_id' => $det['producto_id'],
                    'cantidad_despachada' => $det['cantidad_despachada'],
                    'cantidad_recibida_conforme' => 0.00,
                    'cantidad_merma_transito' => 0.00,
                ]);

                // Asentar movimiento traspaso_salida en bitácora inmutable
                MovimientoInventario::create([
                    'uuid_local' => (string) Str::uuid(),
                    'turno_id' => $turnoOrigen ? $turnoOrigen->id : 1,
                    'sucursal_id' => $origenId,
                    'producto_id' => $det['producto_id'],
                    'tipo_movimiento' => 'traspaso_salida',
                    'cantidad' => $det['cantidad_despachada'],
                    'observaciones' => "Despacho de traspaso #{$traspaso->id} a sucursal destino #{$destinoId}",
                    'fecha_movimiento' => now(),
                ]);
            }

            return [
                'traspaso_id' => $traspaso->id,
                'estado' => 'en_transito',
                'sucursal_origen_id' => $origenId,
                'sucursal_destino_id' => $destinoId,
                'producto_id' => $productoId,
                'cantidad_despachada' => $cantidad,
                'total_items' => count($detallesData),
                'fecha_envio' => $traspaso->fecha_envio->toDateTimeString(),
            ];
        });
    }
}
