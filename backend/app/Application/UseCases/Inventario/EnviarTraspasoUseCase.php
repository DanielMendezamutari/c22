<?php

namespace App\Application\UseCases\Inventario;

use App\Domain\Ports\TraspasoRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;

class EnviarTraspasoUseCase
{
    private TraspasoRepositoryPort $traspasoRepo;

    public function __construct(TraspasoRepositoryPort $traspasoRepo)
    {
        $this->traspasoRepo = $traspasoRepo;
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

            // Soporta multi-item o un solo producto
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

            foreach ($detallesData as $det) {
                \App\Infrastructure\Persistence\Eloquent\Models\TraspasoDetalle::create([
                    'traspaso_id' => $traspaso->id,
                    'producto_id' => $det['producto_id'],
                    'cantidad_despachada' => $det['cantidad_despachada'],
                    'cantidad_recibida_conforme' => 0.00,
                    'cantidad_merma_transito' => 0.00,
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
