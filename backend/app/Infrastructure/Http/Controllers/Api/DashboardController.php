<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AlertaDiscrepancia;
use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class DashboardController extends Controller
{
    /**
     * Retorna métricas en vivo sincronizadas cada 60s para el Super Usuario y Dueño.
     */
    public function metricasTiempoReal(Request $request): JsonResponse
    {
        $sucursales = Sucursal::where('activo', true)->get();
        $sucursalesData = [];

        $totalTurnosActivos = 0;
        $totalBotellasTransformadas = 0;
        $totalComisionesDevengadas = 0.0;
        $totalAlertasCriticas = 0;

        foreach ($sucursales as $sucursal) {
            // Buscar turno activo en la casa
            $turnoActivo = Turno::where('sucursal_id', $sucursal->id)
                ->where('estado', 'abierto')
                ->with(['barman', 'realizadoPor'])
                ->latest('id')
                ->first();

            $turnoData = null;
            $estadoCaja = 'offline';
            $ultimaActividad = null;

            if ($turnoActivo) {
                $totalTurnosActivos++;
                $totalBotellasTransformadas += $turnoActivo->total_transformaciones_netas;
                $totalComisionesDevengadas += (float) $turnoActivo->total_comision_bruta;

                // Último movimiento en barra para medir latencia / conexión de caja
                $ultimoMovimiento = MovimientoInventario::where('turno_id', $turnoActivo->id)
                    ->latest('id')
                    ->first();

                $ultimaFecha = $ultimoMovimiento ? $ultimoMovimiento->created_at : $turnoActivo->fecha_apertura;
                $ultimaActividad = $ultimaFecha ? Carbon::parse($ultimaFecha)->toIso8601String() : null;

                $minutosInactivo = $ultimaFecha ? Carbon::now()->diffInMinutes(Carbon::parse($ultimaFecha)) : 999;
                if ($minutosInactivo <= 5) {
                    $estadoCaja = 'online';
                } elseif ($minutosInactivo <= 30) {
                    $estadoCaja = 'inactivo';
                } else {
                    $estadoCaja = 'alerta_desconexion';
                }

                $duracionMinutos = Carbon::now()->diffInMinutes($turnoActivo->fecha_apertura);
                $horas = floor($duracionMinutos / 60);
                $minutos = $duracionMinutos % 60;

                // Contabilizar movimientos del turno
                $totalBajas = MovimientoInventario::where('turno_id', $turnoActivo->id)
                    ->where('tipo_movimiento', 'baja')
                    ->sum('cantidad');

                $totalIngresos = MovimientoInventario::where('turno_id', $turnoActivo->id)
                    ->whereIn('tipo_movimiento', ['ingreso', 'traspaso_entrada'])
                    ->sum('cantidad');

                $turnoData = [
                    'id' => $turnoActivo->id,
                    'tipo_turno' => $turnoActivo->tipo_turno,
                    'estado' => $turnoActivo->estado,
                    'fecha_apertura' => $turnoActivo->fecha_apertura->toIso8601String(),
                    'tiempo_transcurrido' => "{$horas}h {$minutos}m",
                    'barman' => $turnoActivo->barman ? [
                        'id' => $turnoActivo->barman->id,
                        'nombre_completo' => "{$turnoActivo->barman->nombre} {$turnoActivo->barman->apellido}",
                    ] : null,
                    'es_suplencia' => (bool) $turnoActivo->es_suplencia,
                    'realizado_por' => $turnoActivo->realizadoPor ? "{$turnoActivo->realizadoPor->nombre} {$turnoActivo->realizadoPor->apellido}" : null,
                    'total_transformaciones_netas' => $turnoActivo->total_transformaciones_netas,
                    'total_comision_bruta' => (float) $turnoActivo->total_comision_bruta,
                    'total_bajas' => (float) $totalBajas,
                    'total_ingresos' => (float) $totalIngresos,
                    'permite_reconteo' => (bool) $turnoActivo->permite_reconteo,
                ];
            } else {
                // Último turno cerrado para referencia
                $ultimoTurno = Turno::where('sucursal_id', $sucursal->id)
                    ->where('estado', 'cerrado')
                    ->with('barman')
                    ->latest('fecha_cierre')
                    ->first();

                if ($ultimoTurno) {
                    $turnoData = [
                        'id' => $ultimoTurno->id,
                        'tipo_turno' => $ultimoTurno->tipo_turno,
                        'estado' => 'cerrado',
                        'fecha_cierre' => $ultimoTurno->fecha_cierre ? $ultimoTurno->fecha_cierre->toIso8601String() : null,
                        'barman' => $ultimoTurno->barman ? [
                            'nombre_completo' => "{$ultimoTurno->barman->nombre} {$ultimoTurno->barman->apellido}",
                        ] : null,
                    ];
                }
            }

            // Alertas no resueltas de la sucursal
            $alertasNoResueltas = AlertaDiscrepancia::where('sucursal_id', $sucursal->id)
                ->where('resuelta', false)
                ->count();

            $totalAlertasCriticas += $alertasNoResueltas;

            $sucursalesData[] = [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
                'codigo' => $sucursal->codigo,
                'direccion' => $sucursal->direccion,
                'estado_caja' => $estadoCaja,
                'ultima_actividad' => $ultimaActividad,
                'alertas_pendientes' => $alertasNoResueltas,
                'turno_activo' => $turnoData,
            ];
        }

        return response()->json([
            'success' => true,
            'timestamp' => Carbon::now()->toIso8601String(),
            'proxima_sincronizacion_segundos' => 60,
            'resumen_general' => [
                'total_sucursales' => count($sucursales),
                'total_turnos_activos' => $totalTurnosActivos,
                'total_botellas_transformadas' => $totalBotellasTransformadas,
                'total_comisiones_devengadas_bs' => round($totalComisionesDevengadas, 2),
                'total_alertas_criticas' => $totalAlertasCriticas,
            ],
            'sucursales' => $sucursalesData,
        ]);
    }

    /**
     * Retorna los últimos eventos y logs técnicos del sistema para la consola de Daniel.
     */
    public function logsEnVivo(Request $request): JsonResponse
    {
        $logFile = storage_path('logs/laravel.log');
        $logs = [];

        if (File::exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLines = array_slice($lines, -50);

            foreach (array_reverse($lastLines) as $line) {
                if (preg_match('/^\[(.*?)\] (.*?)\.(.*?): (.*)$/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1],
                        'env' => $matches[2],
                        'nivel' => strtolower($matches[3]),
                        'mensaje' => $matches[4],
                    ];
                } else {
                    $logs[] = [
                        'timestamp' => Carbon::now()->toDateTimeString(),
                        'env' => 'c22',
                        'nivel' => 'info',
                        'mensaje' => $line,
                    ];
                }
            }
        } else {
            $logs[] = [
                'timestamp' => Carbon::now()->toDateTimeString(),
                'env' => 'c22',
                'nivel' => 'info',
                'mensaje' => 'Sistema operativo C22 inicializado y monitoreando en tiempo real.',
            ];
        }

        return response()->json([
            'success' => true,
            'timestamp' => Carbon::now()->toIso8601String(),
            'total_entradas' => count($logs),
            'logs' => $logs,
        ]);
    }
}
