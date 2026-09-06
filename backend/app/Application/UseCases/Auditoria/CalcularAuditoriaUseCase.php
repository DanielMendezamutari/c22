<?php

namespace App\Application\UseCases\Auditoria;

use App\Domain\Entities\AuditoriaVenta;
use App\Domain\Entities\RecetaCombo;
use App\Domain\Ports\AuditoriaRepositoryPort;
use App\Domain\Ports\MovimientoRepositoryPort;
use App\Domain\Ports\RecetaRepositoryPort;
use App\Domain\Ports\TraspasoRepositoryPort;
use App\Domain\Ports\TurnoRepositoryPort;
use DomainException;
use Illuminate\Support\Facades\DB;

class CalcularAuditoriaUseCase
{
    private TurnoRepositoryPort $turnoRepo;
    private MovimientoRepositoryPort $movimientoRepo;
    private RecetaRepositoryPort $recetaRepo;
    private TraspasoRepositoryPort $traspasoRepo;
    private AuditoriaRepositoryPort $auditoriaRepo;

    public function __construct(
        TurnoRepositoryPort $turnoRepo,
        MovimientoRepositoryPort $movimientoRepo,
        RecetaRepositoryPort $recetaRepo,
        TraspasoRepositoryPort $traspasoRepo,
        AuditoriaRepositoryPort $auditoriaRepo
    ) {
        $this->turnoRepo = $turnoRepo;
        $this->movimientoRepo = $movimientoRepo;
        $this->recetaRepo = $recetaRepo;
        $this->traspasoRepo = $traspasoRepo;
        $this->auditoriaRepo = $auditoriaRepo;
    }

    public function ejecutar(array $datos): array
    {
        return DB::transaction(function () use ($datos) {
            $turnoId = (int) $datos['turno_id'];
            $adminId = (int) $datos['admin_id'];
            $productoId = (int) ($datos['producto_id'] ?? 2); // Predeterminado Corona en Botella
            $precioUnitarioSancion = (float) ($datos['precio_unitario_sancion'] ?? 15.00); // 15 Bs por unidad faltante
            $observaciones = $datos['observaciones'] ?? null;

            $turno = $this->turnoRepo->buscarPorId($turnoId);
            if (!$turno) {
                throw new DomainException("El turno {$turnoId} no existe.");
            }

            // 1. Obtener cortes de inventario físico
            $cortesIniciales = $this->turnoRepo->obtenerCortes($turnoId, 'inicial');
            $cortesFinales = $this->turnoRepo->obtenerCortes($turnoId, 'final');

            $stockInicialItem = collect($cortesIniciales)->firstWhere('producto_id', $productoId);
            $stockFinalItem = collect($cortesFinales)->firstWhere('producto_id', $productoId);

            $stockInicial = (float) ($stockInicialItem['cantidad'] ?? 0.00);
            $stockFinal = (float) ($stockFinalItem['cantidad'] ?? 0.00);

            // 2. Obtener movimientos acumulados del turno
            $ingresos = $this->movimientoRepo->obtenerIngresos($turnoId, $productoId);
            $materiaPrimaUsada = $this->movimientoRepo->obtenerConsumoMateriaPrima($turnoId, $productoId);
            $productoTerminado = $this->movimientoRepo->obtenerProduccionTerminada($turnoId, $productoId);
            $bajas = $this->movimientoRepo->obtenerBajasRoturas($turnoId, $productoId);

            // 3. Obtener traspasos netos de la sucursal durante el turno
            $fechaInicio = $turno->fecha_apertura->toDateTimeString();
            $fechaFin = $turno->fecha_cierre ? $turno->fecha_cierre->toDateTimeString() : now()->toDateTimeString();
            $traspasosNetos = $this->traspasoRepo->obtenerTraspasosNetosPorTurno(
                $turno->sucursal_id,
                $productoId,
                $fechaInicio,
                $fechaFin
            );

            // 4. Desglose automático de ventas del Ticket Z (Individuales + Combos)
            $ventasZInput = $datos['ventas_ticket_z'] ?? [];
            $individuales = (float) ($ventasZInput['productos_individuales'][0]['cantidad_vendida'] ?? 0.00);

            $unidadesDesdeCombos = 0.00;
            if (isset($ventasZInput['combos']) && is_array($ventasZInput['combos'])) {
                foreach ($ventasZInput['combos'] as $comboItem) {
                    $comboId = (int) $comboItem['combo_id'];
                    $cantVendida = (int) $comboItem['cantidad_vendida'];

                    $recetaCombo = $this->recetaRepo->buscarComboPorId($comboId);
                    if ($recetaCombo && $recetaCombo->producto_terminado_id === $productoId) {
                        $unidadesDesdeCombos += ($cantVendida * $recetaCombo->unidades_equivalentes);
                    }
                }
            }

            $totalVentasDesglosadas = round($individuales + $unidadesDesdeCombos, 2);

            // 5. Instanciar Entidad de Dominio Puro para ejecutar la Ecuación Constitucional
            $auditoria = new AuditoriaVenta(
                null,
                $turnoId,
                $adminId,
                $productoId,
                $stockInicial,
                $ingresos,
                $traspasosNetos,
                $materiaPrimaUsada,
                $productoTerminado,
                $bajas,
                $stockFinal,
                $totalVentasDesglosadas,
                $observaciones
            );

            $consumoFisico = $auditoria->consumoFisicoCalculado();
            $diferencia = $auditoria->diferencia();
            $resultado = $auditoria->clasificacionResultado();
            $sancionMonto = $auditoria->sancionEconomica($precioUnitarioSancion);
            $colorAlerta = $auditoria->colorAlerta();

            // 6. Asentar Auditoría en base de datos
            $registroAuditoria = $this->auditoriaRepo->registrarAuditoria([
                'turno_id' => $turnoId,
                'admin_id' => $adminId,
                'producto_id' => $productoId,
                'stock_inicial' => $stockInicial,
                'ingresos' => $ingresos,
                'traspasos_netos' => $traspasosNetos,
                'materia_prima_usada' => $materiaPrimaUsada,
                'producto_terminado' => $productoTerminado,
                'bajas' => $bajas,
                'stock_final' => $stockFinal,
                'consumo_fisico_calculado' => $consumoFisico,
                'ventas_ticket_z' => $totalVentasDesglosadas,
                'diferencia' => $diferencia,
                'resultado' => $resultado,
                'sancion_monto' => $sancionMonto,
                'observaciones' => $observaciones,
                'fecha_auditoria' => now(),
            ]);

            // 7. Si hay faltante y sanción > 0, generar registro de sanción
            if ($resultado === 'faltante' && $sancionMonto > 0) {
                $this->auditoriaRepo->registrarSancion([
                    'usuario_id' => $turno->barman_id,
                    'auditoria_id' => $registroAuditoria->id,
                    'monto_sancion' => $sancionMonto,
                    'monto_descontado' => 0.00,
                    'estado' => 'pendiente',
                    'fecha_imputacion' => now(),
                ]);
            }

            // 8. Marcar turno como auditado
            $this->turnoRepo->actualizar($turnoId, ['estado' => 'auditado']);

            return [
                'auditoria_id' => $registroAuditoria->id,
                'turno_id' => $turnoId,
                'barman' => "{$turno->barman->nombre} {$turno->barman->apellido}",
                'producto_id' => $productoId,
                'stock_inicial' => $stockInicial,
                'ingresos' => $ingresos,
                'traspasos_netos' => $traspasosNetos,
                'materia_prima_usada' => $materiaPrimaUsada,
                'producto_terminado' => $productoTerminado,
                'bajas' => $bajas,
                'stock_final' => $stockFinal,
                'consumo_fisico_calculado' => $consumoFisico,
                'ventas_ticket_z_desglosadas' => $totalVentasDesglosadas,
                'ventas_individuales' => $individuales,
                'ventas_por_combos' => $unidadesDesdeCombos,
                'diferencia' => $diferencia,
                'resultado' => $resultado,
                'alerta_color' => $colorAlerta,
                'sancion_generada_bs' => $sancionMonto,
                'mensaje' => match ($resultado) {
                    'cuadrado' => 'Inventario cuadrado a la perfección. Sin observaciones.',
                    'faltante' => "Alerta: Faltante de {$diferencia} unidades. Sanción generada: {$sancionMonto} Bs.",
                    'sobrante' => "Inconsistencia informativa: Sobrante de " . abs($diferencia) . " unidades. Ventas superan consumo físico (sin sanción).",
                },
            ];
        });
    }
}
