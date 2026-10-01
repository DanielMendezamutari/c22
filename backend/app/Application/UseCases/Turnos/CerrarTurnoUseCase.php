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

    public function ejecutar(int $turnoId, array $corteFinal, array $opciones = []): array
    {
        return DB::transaction(function () use ($turnoId, $corteFinal, $opciones) {
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
                $productoId = !empty($item['producto_id']) ? (int) $item['producto_id'] : null;
                $cantidad = (float) $item['cantidad'];
                $esProvisional = (bool) ($item['es_provisional'] ?? false);
                $nombreProvisional = $item['nombre_provisional'] ?? null;
                $esLicor = (bool) ($item['es_licor'] ?? false);

                $fraccion = FraccionLicor::desdeDecimal($cantidad);

                $this->turnoRepo->asentarCorte(
                    $turnoId,
                    $productoId,
                    'cierre',
                    $fraccion->valor(),
                    $esProvisional,
                    $nombreProvisional,
                    $esLicor
                );
            }

            // Cerrar el turno formalmente
            $ahora = now();
            $actualizaciones = [
                'estado' => 'cerrado',
                'fecha_cierre' => $ahora,
            ];

            if (!empty($opciones['cerrado_por_usuario_id'])) {
                $actualizaciones['cerrado_por_usuario_id'] = $opciones['cerrado_por_usuario_id'];
                $actualizaciones['es_suplencia'] = true;
            } elseif (isset($opciones['es_suplencia'])) {
                $actualizaciones['es_suplencia'] = (bool) $opciones['es_suplencia'];
            }

            $this->turnoRepo->actualizar($turnoId, $actualizaciones);

            return [
                'turno_id' => $turnoId,
                'id' => $turnoId,
                'estado' => 'cerrado',
                'fecha_cierre' => $ahora->toDateTimeString(),
                'total_comision_bruta' => (float) ($turno->total_comision_bruta ?? 0),
                'saldo_deudor_descontado' => 0.00,
                'total_neto' => (float) ($turno->total_comision_bruta ?? 0),
                'total_items_corte' => count($corteFinal),
                'cerrado_por_usuario_id' => $actualizaciones['cerrado_por_usuario_id'] ?? $turno->cerrado_por_usuario_id,
                'es_suplencia' => (bool) ($actualizaciones['es_suplencia'] ?? $turno->es_suplencia),
            ];
        });
    }
}
