<?php

namespace App\Application\UseCases\Turnos;

use App\Infrastructure\Persistence\Eloquent\Models\JornalGarzon;
use App\Infrastructure\Persistence\Eloquent\Models\Turno;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use InvalidArgumentException;

class LiquidarJornalGarzonUseCase
{
    /**
     * Liquida inmediatamente el jornal diario del garzón de turno día restando
     * las botellas faltantes calculadas al costo.
     *
     * Fórmula estricta de negocio:
     * Jornal Neto = max(0, Jornal Base - (Faltante Botellas × Costo Unitario))
     */
    public function execute(array $datos): JornalGarzon
    {
        $turnoId = (int) ($datos['turno_id'] ?? 0);
        $usuarioId = (int) ($datos['usuario_id'] ?? 0);

        $turno = Turno::findOrFail($turnoId);
        $usuario = Usuario::findOrFail($usuarioId);

        $jornalBase = (float) ($datos['jornal_base_bs'] ?? 120.00);
        $faltanteUnidades = (float) ($datos['faltante_botellas_unidades'] ?? 0.00);
        $descuentoFaltante = (float) ($datos['descuento_faltante_bs'] ?? 0.00);

        // Si no se proporcionó descuento en Bs explícito pero hay unidades faltantes,
        // calcular a base de costo promedio de 15 Bs por botella estándar si no viene en payload
        if ($descuentoFaltante <= 0 && $faltanteUnidades > 0) {
            $costoUnitario = (float) ($datos['costo_unitario_bs'] ?? 15.00);
            $descuentoFaltante = $faltanteUnidades * $costoUnitario;
        }

        $totalNeto = max(0.00, $jornalBase - $descuentoFaltante);

        $jornal = JornalGarzon::create([
            'turno_id' => $turno->id,
            'usuario_id' => $usuario->id,
            'sucursal_id' => $turno->sucursal_id,
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'jornal_base_bs' => round($jornalBase, 2),
            'faltante_botellas_unidades' => round($faltanteUnidades, 2),
            'descuento_faltante_bs' => round($descuentoFaltante, 2),
            'total_neto_pagado_bs' => round($totalNeto, 2),
            'foto_comprobante_url' => $datos['foto_comprobante_url'] ?? null,
            'estado' => $datos['estado'] ?? 'pagado_en_caja',
        ]);

        return $jornal;
    }
}
