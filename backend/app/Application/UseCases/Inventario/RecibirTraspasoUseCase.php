<?php

namespace App\Application\UseCases\Inventario;

use App\Domain\Ports\TraspasoRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;

class RecibirTraspasoUseCase
{
    private TraspasoRepositoryPort $traspasoRepo;

    public function __construct(TraspasoRepositoryPort $traspasoRepo)
    {
        $this->traspasoRepo = $traspasoRepo;
    }

    public function ejecutar(int $traspasoId, array $datos): array
    {
        return DB::transaction(function () use ($traspasoId, $datos) {
            $traspaso = $this->traspasoRepo->buscarPorId($traspasoId);
            if (!$traspaso) {
                throw new DomainException("Traspaso #{$traspasoId} no encontrado.");
            }

            if ($traspaso->estado !== 'en_transito') {
                throw new DomainException("El traspaso no se encuentra en tránsito (estado actual: {$traspaso->estado}).");
            }

            $items = $datos['items'] ?? null;
            $totalConforme = 0.00;
            $totalMerma = 0.00;

            if (!empty($items) && is_array($items)) {
                foreach ($items as $it) {
                    $c = (float) ($it['cantidad_recibida_conforme'] ?? 0.00);
                    $m = (float) ($it['cantidad_merma_transito'] ?? 0.00);
                    if ($c < 0 || $m < 0) {
                        throw new DomainException("Las cantidades no pueden ser negativas.");
                    }
                    $totalConforme += $c;
                    $totalMerma += $m;

                    // Actualizar detalle específico
                    $query = \App\Infrastructure\Persistence\Eloquent\Models\TraspasoDetalle::where('traspaso_id', $traspasoId);
                    if (isset($it['detalle_id'])) {
                        $query->where('id', $it['detalle_id']);
                    } elseif (isset($it['producto_id'])) {
                        $query->where('producto_id', $it['producto_id']);
                    }
                    $detalle = $query->first();
                    if ($detalle) {
                        $detalle->update([
                            'cantidad_recibida_conforme' => $c,
                            'cantidad_merma_transito' => $m,
                        ]);
                    }
                }
                $conforme = $totalConforme;
                $merma = $totalMerma;
            } else {
                $conforme = (float) ($datos['cantidad_recibida_conforme'] ?? 0.00);
                $merma = (float) ($datos['cantidad_merma_transito'] ?? 0.00);

                if ($conforme < 0 || $merma < 0) {
                    throw new DomainException("Las cantidades recibidas o mermadas no pueden ser negativas.");
                }

                // Si hay un solo detalle registrado, sincronizarlo
                $primerDetalle = \App\Infrastructure\Persistence\Eloquent\Models\TraspasoDetalle::where('traspaso_id', $traspasoId)->first();
                if ($primerDetalle) {
                    $primerDetalle->update([
                        'cantidad_recibida_conforme' => $conforme,
                        'cantidad_merma_transito' => $merma,
                    ]);
                }
            }

            $receptorId = (int) $datos['usuario_receptor_id'];
            $fotoRecepcion = $datos['foto_recepcion'] ?? null;

            $totalContado = round($conforme + $merma, 2);
            $despachado = round((float) $traspaso->cantidad_despachada, 2);

            // Se asienta la recepción formal
            $this->traspasoRepo->recibir(
                $traspasoId,
                $conforme,
                $merma,
                $receptorId,
                $fotoRecepcion
            );

            $estadoFinal = ($merma > 0 || $totalContado !== $despachado)
                ? 'recibido_con_discrepancia'
                : 'recibido_conforme';

            return [
                'traspaso_id' => $traspasoId,
                'estado' => $estadoFinal,
                'cantidad_despachada' => $despachado,
                'stock_acreditado_destino' => $conforme,
                'merma_asentada' => $merma,
                'diferencia_no_justificada' => round($despachado - $totalContado, 2),
                'fecha_recepcion' => now()->toDateTimeString(),
            ];
        });
    }
}
