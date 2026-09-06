<?php

namespace App\Application\UseCases\Turnos;

use App\Domain\Ports\TurnoRepositoryPort;
use App\Domain\ValueObjects\FraccionLicor;
use DomainException;
use Illuminate\Support\Facades\DB;

class CerrarTurnoUseCase
{
    private TurnoRepositoryPort $turnoRepo;

    public function __construct(TurnoRepositoryPort $turnoRepo)
    {
        $this->turnoRepo = $turnoRepo;
    }

    public function ejecutar(int $turnoId, array $corteFinal): array
    {
        return DB::transaction(function () use ($turnoId, $corteFinal) {
            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno) {
                throw new DomainException("Turno no encontrado.");
            }

            // Regla de Inmutabilidad: Un turno cerrado no puede ser alterado
            if ($turno->estado === 'cerrado') {
                throw new DomainException("El turno {$turnoId} ya se encuentra cerrado. Los cortes finales son inmutables.");
            }

            // Asentar cortes finales
            foreach ($corteFinal as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad = (float) $item['cantidad'];

                $fraccion = FraccionLicor::desdeDecimal($cantidad);

                $this->turnoRepo->asentarCorte(
                    $turnoId,
                    $productoId,
                    'cierre',
                    $fraccion->valor()
                );
            }

            // Cerrar el turno formalmente
            $ahora = now();
            $this->turnoRepo->actualizar($turnoId, [
                'estado' => 'cerrado',
                'fecha_cierre' => $ahora,
            ]);

            return [
                'turno_id' => $turnoId,
                'estado' => 'cerrado',
                'fecha_cierre' => $ahora->toDateTimeString(),
                'total_comision_bruta' => (float) $turno->total_comision,
                'saldo_deudor_descontado' => 0.00,
                'total_neto' => (float) $turno->total_comision,
                'total_items_corte' => count($corteFinal),
            ];
        });
    }
}
