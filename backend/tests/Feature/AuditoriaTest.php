<?php

namespace Tests\Feature;

use App\Domain\Entities\AuditoriaVenta;
use App\Domain\Entities\RecetaCombo;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    public function test_desglose_combos_y_balance_perfecto_verde(): void
    {
        // 5 baldes x 6 unidades = 30 botellas
        // 10 individuales = 10 botellas
        // Total ventas Ticket Z = 40 botellas
        $combo = new RecetaCombo(1, 'Balde Corona x 6', 2, 6);
        $totalVentasDesglosadas = $combo->desglosarAUnidades(5) + 10.0;
        $this->assertEquals(40.0, $totalVentasDesglosadas);

        // Balance físico: Inicial(24) + Ingresos(48) + Terminado(12) - Bajas(1) - Final(43) = 40
        $auditoria = new AuditoriaVenta(
            id: null,
            turnoId: 1,
            adminId: 2,
            productoId: 2,
            stockInicial: 24.0,
            ingresos: 48.0,
            traspasosNetos: 0.0,
            materiaPrimaUsada: 0.0,
            productoTerminado: 12.0,
            bajas: 1.0,
            stockFinal: 43.0,
            ventasTicketZ: $totalVentasDesglosadas
        );

        $this->assertEquals(40.0, $auditoria->consumoFisicoCalculado());
        $this->assertEquals(0.0, $auditoria->diferencia());
        $this->assertEquals('cuadrado', $auditoria->clasificacionResultado());
        $this->assertEquals('verde', $auditoria->colorAlerta());
        $this->assertEquals(0.0, $auditoria->sancionEconomica(10.50));
    }

    public function test_faltante_genera_alerta_roja_y_sancion(): void
    {
        // Consumo físico: 24 + 48 + 12 - 1 - 41 = 42
        // Ventas: 40 => Faltante de 2 botellas (diferencia = +2)
        $auditoria = new AuditoriaVenta(
            id: null,
            turnoId: 1,
            adminId: 2,
            productoId: 2,
            stockInicial: 24.0,
            ingresos: 48.0,
            traspasosNetos: 0.0,
            materiaPrimaUsada: 0.0,
            productoTerminado: 12.0,
            bajas: 1.0,
            stockFinal: 41.0,
            ventasTicketZ: 40.0
        );

        $this->assertEquals(42.0, $auditoria->consumoFisicoCalculado());
        $this->assertEquals(2.0, $auditoria->diferencia());
        $this->assertEquals('faltante', $auditoria->clasificacionResultado());
        $this->assertEquals('rojo', $auditoria->colorAlerta());
        $this->assertEquals(20.00, $auditoria->sancionEconomica(10.00));
    }

    public function test_sobrante_genera_alerta_azul_sin_sancion_al_barman(): void
    {
        // Consumo físico: 40
        // Ventas: 45 => Sobrante (diferencia = -5)
        // Regla Constitucional FR-020: Sobrante informativo, alerta azul, CERO sanción.
        $auditoria = new AuditoriaVenta(
            id: null,
            turnoId: 1,
            adminId: 2,
            productoId: 2,
            stockInicial: 24.0,
            ingresos: 48.0,
            traspasosNetos: 0.0,
            materiaPrimaUsada: 0.0,
            productoTerminado: 12.0,
            bajas: 1.0,
            stockFinal: 43.0,
            ventasTicketZ: 45.0
        );

        $this->assertEquals(40.0, $auditoria->consumoFisicoCalculado());
        $this->assertEquals(-5.0, $auditoria->diferencia());
        $this->assertEquals('sobrante', $auditoria->clasificacionResultado());
        $this->assertEquals('azul', $auditoria->colorAlerta());
        $this->assertEquals(0.00, $auditoria->sancionEconomica(10.00));
    }
}
