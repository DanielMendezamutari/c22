<?php

namespace App\Application\UseCases\Auditoria;

use App\Domain\Ports\AuditoriaRepositoryPort;
use App\Domain\Ports\UsuarioRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarLiquidacionSemanalUseCase
{
    private AuditoriaRepositoryPort $auditoriaRepo;
    private UsuarioRepositoryPort $usuarioRepo;

    public function __construct(
        AuditoriaRepositoryPort $auditoriaRepo,
        UsuarioRepositoryPort $usuarioRepo
    ) {
        $this->auditoriaRepo = $auditoriaRepo;
        $this->usuarioRepo = $usuarioRepo;
    }

    public function ejecutar(?string $semanaAno = null, ?int $sucursalId = null, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        // 1. Determinar rango de fechas
        if (!$fechaInicio || !$fechaFin) {
            if ($semanaAno && str_contains($semanaAno, '-W')) {
                $parts = explode('-W', $semanaAno);
                $carbon = Carbon::now()->setISODate((int)$parts[0], (int)$parts[1]);
                $fechaInicio = $carbon->startOfWeek()->toDateString();
                $fechaFin = $carbon->endOfWeek()->toDateString();
            } else {
                $now = Carbon::now();
                $fechaInicio = $now->copy()->startOfWeek()->toDateString();
                $fechaFin = $now->copy()->endOfWeek()->toDateString();
                $semanaAno = "{$now->year}-W" . str_pad($now->weekOfYear, 2, '0', STR_PAD_LEFT);
            }
        }

        // 2. Buscar barmen (especialmente titulares de noche y personal de barra)
        $queryBarmen = Usuario::where('activo', true)
            ->whereIn('rol', ['barman', 'garzon']);

        if ($sucursalId && $sucursalId > 0) {
            $queryBarmen->where('sucursal_actual_id', $sucursalId);
        }

        $barmen = $queryBarmen->with('sucursalActual:id,nombre')->get();
        $liquidaciones = [];

        foreach ($barmen as $barman) {
            // Contar turnos trabajados en el rango de fechas
            $queryTurnos = Turno::where('barman_id', $barman->id)
                ->whereBetween('fecha_apertura', ["{$fechaInicio} 00:00:00", "{$fechaFin} 23:59:59"]);

            if ($sucursalId && $sucursalId > 0) {
                $queryTurnos->where('sucursal_id', $sucursalId);
            }

            $turnos = $queryTurnos->get();
            $turnosNoche = $turnos->where('tipo_turno', 'noche')->count();
            $turnosDia = $turnos->where('tipo_turno', 'dia')->count();

            // Sueldo base semanal
            $sueldoBase = (float) ($barman->sueldo_base_semanal > 0
                ? $barman->sueldo_base_semanal
                : ($barman->rol === 'barman' ? 700.00 : 0.00));

            // Sanciones acumuladas por faltantes de inventario
            $sancionesPendientes = $this->auditoriaRepo->obtenerSancionesPendientes($barman->id);
            $totalSanciones = (float) collect($sancionesPendientes)->sum(function ($s) {
                return $s['monto_sancion'] - $s['monto_descontado'];
            });

            // En turno noche se descuentan las sanciones del sueldo semanal
            $sancionesADescontar = min($sueldoBase, $totalSanciones);
            $sueldoNetoAPagar = max(0.00, round($sueldoBase - $sancionesADescontar, 2));

            $liquidaciones[] = [
                'barman_id' => $barman->id,
                'barman' => "{$barman->nombre} {$barman->apellido}",
                'rol' => $barman->rol,
                'sucursal' => $barman->sucursalActual ? $barman->sucursalActual->nombre : 'Global / Rotativa',
                'turnos_noche_trabajados' => $turnosNoche,
                'turnos_dia_trabajados' => $turnosDia,
                'sueldo_base_semanal' => $sueldoBase,
                'total_sanciones_faltantes' => $totalSanciones,
                'sanciones_descontadas' => $sancionesADescontar,
                'sueldo_neto_a_pagar' => $sueldoNetoAPagar,
                'saldo_deudor_remanente' => max(0.00, round($totalSanciones - $sancionesADescontar, 2)),
                'estado' => 'pendiente',
            ];
        }

        return [
            'periodo' => "{$fechaInicio} al {$fechaFin}",
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'semana_ano' => $semanaAno,
            'liquidaciones' => $liquidaciones,
        ];
    }
}
