<?php

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Ports\AuditoriaRepositoryPort;
use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaVenta;
use App\Infrastructure\Persistence\Eloquent\Models\SancionInventario;
use App\Infrastructure\Persistence\Eloquent\Models\LiquidacionSemanal;
use App\Infrastructure\Persistence\Eloquent\Models\MovimientoInventario;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Support\Facades\DB;

class EloquentAuditoriaRepository implements AuditoriaRepositoryPort
{
    public function registrarAuditoria(array $datos): AuditoriaVenta
    {
        return AuditoriaVenta::create($datos);
    }

    public function buscarPorTurnoId(int $turnoId): ?AuditoriaVenta
    {
        return AuditoriaVenta::with(['turno.barman', 'admin', 'producto', 'sancion'])
            ->where('turno_id', $turnoId)
            ->first();
    }

    public function registrarSancion(array $datos): SancionInventario
    {
        $sancion = SancionInventario::create($datos);

        // Actualizar saldo deudor del usuario
        Usuario::where('id', $datos['usuario_id'])
            ->increment('saldo_deudor_acumulado', $datos['monto_sancion']);

        return $sancion;
    }

    public function obtenerSancionesPendientes(int $usuarioId): array
    {
        return SancionInventario::where('usuario_id', $usuarioId)
            ->where('estado', 'pendiente')
            ->get()
            ->toArray();
    }

    public function marcarSancionComoDescontada(int $sancionId, float $monto, string $modo): bool
    {
        $sancion = SancionInventario::findOrFail($sancionId);
        $nuevoDescontado = $sancion->monto_descontado + $monto;
        $estado = ($nuevoDescontado >= $sancion->monto_sancion)
            ? ($modo === 'diario' ? 'descontado_caja' : 'descontado_semanal')
            : 'pendiente';

        $actualizado = $sancion->update([
            'monto_descontado' => $nuevoDescontado,
            'estado' => $estado,
            'fecha_liquidacion' => now(),
        ]);

        if ($actualizado) {
            Usuario::where('id', $sancion->usuario_id)
                ->decrement('saldo_deudor_acumulado', $monto);
        }

        return $actualizado;
    }

    public function generarLiquidacionSemanal(array $datos): LiquidacionSemanal
    {
        return LiquidacionSemanal::create($datos);
    }

    public function obtenerLiquidacionSemanal(string $semana, int $sucursalId): array
    {
        return LiquidacionSemanal::where('semana_ano', $semana)
            ->with('usuario')
            ->get()
            ->toArray();
    }

    public function obtenerReporteRatios(int $sucursalId, ?int $productoDestinoId = null): array
    {
        // Obtener ratios empíricos por barman en la sucursal
        $query = DB::table('movimientos_inventario as m')
            ->join('turnos as t', 'm.turno_id', '=', 't.id')
            ->join('usuarios as u', 't.barman_id', '=', 'u.id')
            ->select(
                'u.id as barman_id',
                'u.nombre as barman_nombre',
                'u.apellido as barman_apellido',
                DB::raw('COUNT(DISTINCT m.turno_id) as turnos_con_transformacion'),
                DB::raw('AVG(m.ratio_calculado) as ratio_promedio'),
                DB::raw('MIN(m.ratio_calculado) as ratio_minimo'),
                DB::raw('MAX(m.ratio_calculado) as ratio_maximo'),
                DB::raw('SUM(CASE WHEN m.tipo_movimiento = "transformacion_consumo" THEN m.cantidad ELSE 0 END) as total_materia_prima_usada'),
                DB::raw('SUM(CASE WHEN m.tipo_movimiento = "transformacion_produccion" THEN m.cantidad ELSE 0 END) as total_producto_terminado_producido')
            )
            ->where('m.sucursal_id', $sucursalId)
            ->whereNotNull('m.ratio_calculado')
            ->groupBy('u.id', 'u.nombre', 'u.apellido');

        return $query->get()->toArray();
    }
}
