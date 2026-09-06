<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Entidad Pura de Dominio que materializa la Ecuación Constitucional de Balance de Masa.
 * Formula: Consumo Físico = (Stock Inicial + Ingresos + Traspasos - Materia Prima + Prod. Terminado) - Bajas - Stock Final
 */
class AuditoriaVenta
{
    private ?int $id;
    private int $turnoId;
    private int $adminId;
    private int $productoId;
    private float $stockInicial;
    private float $ingresos;
    private float $traspasosNetos;
    private float $materiaPrimaUsada;
    private float $productoTerminado;
    private float $bajas;
    private float $stockFinal;
    private float $ventasTicketZ;
    private ?string $observaciones;

    public function __construct(
        ?int $id,
        int $turnoId,
        int $adminId,
        int $productoId,
        float $stockInicial,
        float $ingresos,
        float $traspasosNetos,
        float $materiaPrimaUsada,
        float $productoTerminado,
        float $bajas,
        float $stockFinal,
        float $ventasTicketZ,
        ?string $observaciones = null
    ) {
        $this->id = $id;
        $this->turnoId = $turnoId;
        $this->adminId = $adminId;
        $this->productoId = $productoId;
        $this->stockInicial = round($stockInicial, 2);
        $this->ingresos = round($ingresos, 2);
        $this->traspasosNetos = round($traspasosNetos, 2);
        $this->materiaPrimaUsada = round($materiaPrimaUsada, 2);
        $this->productoTerminado = round($productoTerminado, 2);
        $this->bajas = round($bajas, 2);
        $this->stockFinal = round($stockFinal, 2);
        $this->ventasTicketZ = round($ventasTicketZ, 2);
        $this->observaciones = $observaciones;
    }

    /**
     * Calcula el consumo físico real que debió ocurrir según los conteos y movimientos de barra.
     */
    public function consumoFisicoCalculado(): float
    {
        $disponible = ($this->stockInicial + $this->ingresos + $this->traspasosNetos - $this->materiaPrimaUsada + $this->productoTerminado);
        $salidaReal = $disponible - $this->bajas - $this->stockFinal;
        return round($salidaReal, 2);
    }

    /**
     * Diferencia = Consumo Físico - Ventas Reales declaradas en Ticket Z.
     */
    public function diferencia(): float
    {
        return round($this->consumoFisicoCalculado() - $this->ventasTicketZ, 2);
    }

    /**
     * Clasificación constitucional:
     * - 'cuadrado': diferencia == 0.00
     * - 'faltante': diferencia > 0.00 (Físico salió más de lo vendido por caja -> alerta roja con sanción)
     * - 'sobrante': diferencia < 0.00 (Ventas superan salida física -> alerta azul/amarillo informativa, sin sanción)
     */
    public function clasificacionResultado(): string
    {
        $diff = $this->diferencia();
        if (abs($diff) < 0.01) {
            return 'cuadrado';
        }
        if ($diff > 0) {
            return 'faltante';
        }
        return 'sobrante';
    }

    /**
     * Sanción económica aplicable al barman en Bs.
     * Si es sobrante o cuadrado, la sanción es estrictamente 0.00 Bs (Principio FR-020).
     */
    public function sancionEconomica(float $precioReferenciaPorUnidad): float
    {
        if ($this->clasificacionResultado() === 'faltante') {
            return round($this->diferencia() * $precioReferenciaPorUnidad, 2);
        }
        return 0.00;
    }

    public function colorAlerta(): string
    {
        return match ($this->clasificacionResultado()) {
            'cuadrado' => 'verde',
            'faltante' => 'rojo',
            'sobrante' => 'azul',
            default => 'gris',
        };
    }
}
