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
                'realizado_por_usuario_id' => $comando['realizado_por_usuario_id'] ?? null,
                'es_suplencia' => (bool) ($comando['es_suplencia'] ?? false),
            ]);

            // Asentar cortes iniciales
            foreach ($corteInicial as $item) {
                $productoId = !empty($item['producto_id']) ? (int) $item['producto_id'] : null;
                $cantidad = (float) $item['cantidad'];
                $esProvisional = (bool) ($item['es_provisional'] ?? false);
                $nombreProvisional = $item['nombre_provisional'] ?? null;
                $esLicor = (bool) ($item['es_licor'] ?? false);

                // Validación estricta de fracción
                $fraccion = FraccionLicor::desdeDecimal($cantidad);

                $this->turnoRepo->asentarCorte(
                    $turno->id,
                    $productoId,
                    'apertura',
                    $fraccion->valor(),
                    $esProvisional,
                    $nombreProvisional,
                    $esLicor
                );
            }

            return [
                'turno_id' => $turno->id,
                'id' => $turno->id,
                'estado' => 'abierto',
                'tipo_turno' => $tipoTurno,
                'sucursal_id' => $sucursalId,
                'barman_id' => $barmanId,
                'realizado_por_usuario_id' => $turno->realizado_por_usuario_id ?? $barmanId,
                'es_suplencia' => (bool) ($turno->es_suplencia ?? false),
                'fecha_apertura' => $turno->fecha_apertura->toDateTimeString(),
                'total_items_corte' => count($corteInicial),
            ];
        });
    }
}
