<?php

namespace App\Application\UseCases\Auditoria;

use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaConciliacionTriangulada;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use App\Infrastructure\Persistence\Eloquent\Models\PosProductoMapeo;
use App\Infrastructure\Persistence\Eloquent\Models\PosTransaccion;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\VoucherDeposito;

class AuditoriaConciliacionTrianguladaUseCase
{
    public function execute(int $turnoId): array
    {
        $turno = Turno::with(['sucursal', 'usuario', 'cerradoPorUsuario'])->findOrFail($turnoId);

        // 1. Vértice 1: Transacciones POS (SQL Server)
        $queryTrans = PosTransaccion::where('sucursal_id', $turno->sucursal_id);
        if ($turno->fecha_cierre) {
            $queryTrans->whereBetween('fecha_hora', [$turno->fecha_apertura, $turno->fecha_cierre]);
        } else {
            $queryTrans->where('fecha_hora', '>=', $turno->fecha_apertura);
        }
        $transacciones = $queryTrans->get();

        $totalPosVentas = (float)$transacciones->sum('subtotal');
        $totalPosEfectivo = (float)$transacciones->where('metodo_pago', 'efectivo')->sum('subtotal');
        $totalPosQr = (float)$transacciones->where('metodo_pago', 'qr')->sum('subtotal');
        $totalPosTarjeta = (float)$transacciones->where('metodo_pago', 'tarjeta')->sum('subtotal');

        // Desglose de botellas vendidas según mapeo y combos
        $mapeos = PosProductoMapeo::with(['combo.detalles'])
            ->where('sucursal_id', $turno->sucursal_id)
            ->where('activo', true)
            ->get()
            ->keyBy('pos_producto_id');

        $botellasVendidasPos = 0.0;
        foreach ($transacciones as $t) {
            $mapeo = $mapeos->get($t->pos_producto_id);
            if ($mapeo) {
                if ($mapeo->c22_combo_id && $mapeo->combo) {
                    foreach ($mapeo->combo->detalles as $det) {
                        $botellasVendidasPos += ((float)$t->cantidad * (float)$det->cantidad);
                    }
                } else {
                    $botellasVendidasPos += (float)$t->cantidad;
                }
            } else {
                $botellasVendidasPos += (float)$t->cantidad;
            }
        }

        // 2. Vértice 2: Planilla Manual de Caja (OCR Gemini) y Vouchers
        $planilla = PlanillaCaja::where('turno_id', $turno->id)->first();
        $voucher = VoucherDeposito::where('turno_id', $turno->id)->first();

        $totalPlanillaEfectivo = $planilla ? (float)$planilla->total_ventas_declaradas_bs : 0.0;
        $totalGastosPlanilla = $planilla ? (float)$planilla->total_gastos_declarados_bs : 0.0;
        $montoSobreEfectivo = $planilla ? (float)$planilla->monto_sobre_efectivo_bs : 0.0;
        $totalVoucherDeposito = $voucher ? (float)$voucher->monto_depositado_bs : 0.0;

        // Diferencia de Caja: Efectivo cobrado por POS vs Efectivo anotado en planilla
        $diferenciaCajaBs = $totalPlanillaEfectivo - $totalPosEfectivo;

        // 3. Vértice 3: Conteo Físico en Barra (Barman)
        // Consumo físico según balance del turno
        $corteApertura = $turno->cortesInventario()->where('tipo_corte', 'apertura')->sum('cantidad');
        $corteCierre = $turno->cortesInventario()->where('tipo_corte', 'cierre')->sum('cantidad');
        $ingresosMercaderia = $turno->movimientosInventario()->where('tipo', 'ingreso_compra')->sum('cantidad');
        $bajasBarra = $turno->movimientosInventario()->where('tipo', 'baja_rotura')->sum('cantidad');

        $botellasConsumoBarra = ($corteApertura + $ingresosMercaderia - $bajasBarra) - $corteCierre;
        if ($botellasConsumoBarra < 0) {
            $botellasConsumoBarra = 0.0;
        }

        // Diferencia de Botellas: Lo que vendió el POS vs Lo que salió de la barra
        $diferenciaBotellas = $botellasVendidasPos - $botellasConsumoBarra;

        // 4. Semáforo y Responsabilidad
        $responsableCajaId = $turno->cerrado_por_usuario_id ?: $turno->usuario_id;
        $responsableBarraId = $turno->usuario_id;

        $estadoSemaforo = 'verde_cuadrado';
        $imputadoCaja = null;
        $imputadoBarra = null;

        if (abs($diferenciaCajaBs) > 20) {
            $estadoSemaforo = ($diferenciaCajaBs < -50) ? 'rojo_discrepancia' : 'ambar_observado';
            $imputadoCaja = $turno->cerradoPorUsuario?->nombre ?: 'Cajera de Turno';
        }

        if (abs($diferenciaBotellas) > 2) {
            $estadoSemaforo = 'rojo_discrepancia';
            $imputadoBarra = $turno->usuario?->nombre . ' ' . $turno->usuario?->apellido . ' (Barman)';
        }

        // 5. Guardar o actualizar registro en base de datos
        $registro = AuditoriaConciliacionTriangulada::updateOrCreate(
            ['turno_id' => $turno->id],
            [
                'sucursal_id' => $turno->sucursal_id,
                'total_pos_ventas_bs' => $totalPosVentas,
                'total_planilla_efectivo_bs' => $totalPlanillaEfectivo,
                'total_voucher_deposito_bs' => $totalVoucherDeposito,
                'diferencia_caja_bs' => $diferenciaCajaBs,
                'responsable_caja_usuario_id' => $responsableCajaId,
                'botellas_vendidas_pos' => $botellasVendidasPos,
                'botellas_consumidas_inventario' => $botellasConsumoBarra,
                'diferencia_botellas' => $diferenciaBotellas,
                'responsable_barra_usuario_id' => $responsableBarraId,
                'estado_semaforo' => $estadoSemaforo,
            ]
        );

        return [
            'id' => $registro->id,
            'turno_id' => $turno->id,
            'sucursal_id' => $turno->sucursal_id,
            'sucursal_nombre' => $turno->sucursal?->nombre,
            'fecha_turno' => $turno->fecha_apertura ? date('Y-m-d', strtotime($turno->fecha_apertura)) : date('Y-m-d'),
            'hora_apertura' => $turno->fecha_apertura,
            'hora_cierre' => $turno->fecha_cierre,
            'estado_turno' => $turno->estado,
            'responsables' => [
                'barra' => $turno->usuario ? ($turno->usuario->nombre . ' ' . $turno->usuario->apellido) : 'Barman',
                'caja' => $turno->cerradoPorUsuario ? ($turno->cerradoPorUsuario->nombre . ' ' . $turno->cerradoPorUsuario->apellido) : 'Cajera',
            ],
            'vertice_1_pos' => [
                'ventas_brutas_bs' => $totalPosVentas,
                'efectivo_bs' => $totalPosEfectivo,
                'qr_bs' => $totalPosQr,
                'tarjeta_bs' => $totalPosTarjeta,
                'transacciones_count' => $transacciones->count(),
                'botellas_vendidas_equiv' => round($botellasVendidasPos, 2),
            ],
            'vertice_2_planilla' => [
                'efectivo_declarado_bs' => $totalPlanillaEfectivo,
                'gastos_caja_chica_bs' => $totalGastosPlanilla,
                'neto_sobre_declarado_bs' => $montoSobreEfectivo,
                'voucher_depositado_banco_bs' => $totalVoucherDeposito,
                'tiene_foto_planilla' => !empty($planilla?->foto_url),
                'tiene_foto_voucher' => !empty($voucher?->foto_url),
            ],
            'vertice_3_inventario' => [
                'apertura_stock' => (float)$corteApertura,
                'ingresos_stock' => (float)$ingresosMercaderia,
                'bajas_stock' => (float)$bajasBarra,
                'cierre_stock' => (float)$corteCierre,
                'consumo_fisico_botellas' => round($botellasConsumoBarra, 2),
            ],
            'auditoria_financiera' => [
                'diferencia_efectivo_bs' => round($diferenciaCajaBs, 2),
                'estado' => $diferenciaCajaBs < -20 ? 'faltante_caja' : ($diferenciaCajaBs > 20 ? 'sobrante_caja' : 'cuadrado'),
                'imputado_a' => $imputadoCaja,
            ],
            'auditoria_botellas' => [
                'diferencia_unidades' => round($diferenciaBotellas, 2),
                'estado' => $diferenciaBotellas < -1 ? 'faltante_botellas' : ($diferenciaBotellas > 1 ? 'sobrante_botellas' : 'cuadrado'),
                'imputado_a' => $imputadoBarra,
            ],
            'estado_semaforo' => $estadoSemaforo,
        ];
    }
}
