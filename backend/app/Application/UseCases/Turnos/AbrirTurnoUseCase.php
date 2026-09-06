<?php

namespace App\Application\UseCases\Turnos;

use App\Domain\Ports\TurnoRepositoryPort;
use App\Domain\ValueObjects\FraccionLicor;
use DomainException;
use Illuminate\Support\Facades\DB;

class AbrirTurnoUseCase
{
    private TurnoRepositoryPort $turnoRepo;

    public function __construct(TurnoRepositoryPort $turnoRepo)
    {
        $this->turnoRepo = $turnoRepo;
    }

    public function ejecutar(array $comando): array
    {
        return DB::transaction(function () use ($comando) {
            $sucursalId = (int) $comando['sucursal_id'];
            $barmanId = (int) $comando['barman_id'];
            $tipoTurno = $comando['tipo_turno'] ?? 'dia';
            $corteInicial = $comando['corte_inicial'] ?? [];

            // Verificar si el barman ya tiene un turno abierto
            $turnoExistente = $this->turnoRepo->buscarTurnoActivoPorBarman($barmanId, $sucursalId);
            if ($turnoExistente && $turnoExistente->estado === 'abierto') {
                throw new DomainException("El barman ya tiene un turno abierto (#{$turnoExistente->id}) en esta sucursal.");
            }

            // Crear el turno
            $turno = $this->turnoRepo->crear([
                'sucursal_id' => $sucursalId,
                'barman_id' => $barmanId,
                'tipo_turno' => $tipoTurno,
                'estado' => 'abierto',
                'fecha_apertura' => now(),
            ]);

            // Asentar cortes iniciales
            foreach ($corteInicial as $item) {
                $productoId = (int) $item['producto_id'];
                $cantidad = (float) $item['cantidad'];

                // Validación estricta de fracción
                $fraccion = FraccionLicor::desdeDecimal($cantidad);

                $this->turnoRepo->asentarCorte(
                    $turno->id,
                    $productoId,
                    'apertura',
                    $fraccion->valor()
                );
            }

            return [
                'turno_id' => $turno->id,
                'estado' => 'abierto',
                'tipo_turno' => $tipoTurno,
                'sucursal_id' => $sucursalId,
                'barman_id' => $barmanId,
                'fecha_apertura' => $turno->fecha_apertura->toDateTimeString(),
                'total_items_corte' => count($corteInicial),
            ];
        });
    }
}
