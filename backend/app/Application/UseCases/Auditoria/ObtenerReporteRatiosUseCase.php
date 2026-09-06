<?php

namespace App\Application\UseCases\Auditoria;

use App\Domain\Ports\AuditoriaRepositoryPort;

class ObtenerReporteRatiosUseCase
{
    private AuditoriaRepositoryPort $auditoriaRepo;

    // Tolerancia estándar de merma: ratio ideal ~1.167 (14 latas para 12 botellas).
    // Tolerancia máxima aceptada: 1.25. Superior a eso activa alerta de merma anómala.
    public const UMBRAL_ALERTA_MERMA = 1.25;

    public function __construct(AuditoriaRepositoryPort $auditoriaRepo)
    {
        $this->auditoriaRepo = $auditoriaRepo;
    }

    public function ejecutar(int $sucursalId, ?int $productoDestinoId = null): array
    {
        $datosCrudos = $this->auditoriaRepo->obtenerReporteRatios($sucursalId, $productoDestinoId);

        $reporte = [];
        foreach ($datosCrudos as $fila) {
            $filaArray = (array) $fila;
            $ratioPromedio = $filaArray['ratio_promedio'] !== null ? round((float) $filaArray['ratio_promedio'], 3) : 0.0;
            $alertaMerma = $ratioPromedio > self::UMBRAL_ALERTA_MERMA;

            $reporte[] = [
                'barman_id' => $filaArray['barman_id'],
                'barman' => trim($filaArray['barman_nombre'] . ' ' . ($filaArray['barman_apellido'] ?? '')),
                'turnos_con_transformacion' => (int) $filaArray['turnos_con_transformacion'],
                'ratio_promedio' => $ratioPromedio,
                'ratio_minimo' => $filaArray['ratio_minimo'] !== null ? round((float) $filaArray['ratio_minimo'], 3) : 0.0,
                'ratio_maximo' => $filaArray['ratio_maximo'] !== null ? round((float) $filaArray['ratio_maximo'], 3) : 0.0,
                'total_insumo_usado' => (float) ($filaArray['total_materia_prima_usada'] ?? 0),
                'total_terminado_producido' => (float) ($filaArray['total_producto_terminado_producido'] ?? 0),
                'alerta_merma' => $alertaMerma,
                'estado_tolerancia' => $alertaMerma ? 'merma_anomala' : 'optimo',
            ];
        }

        return [
            'sucursal_id' => $sucursalId,
            'umbral_merma_maximo' => self::UMBRAL_ALERTA_MERMA,
            'total_barmen_evaluados' => count($reporte),
            'barmen' => $reporte,
        ];
    }
}
