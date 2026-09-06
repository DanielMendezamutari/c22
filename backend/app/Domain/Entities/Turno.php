<?php

namespace App\Domain\Entities;

use App\Domain\Enums\EstadoTurno;
use App\Domain\Enums\TipoTurno;
use DateTimeImmutable;
use DomainException;

/**
 * Entidad Pura de Dominio (POPO) para Turno de 12 Horas.
 * Aislada de frameworks y persistencia (Arquitectura Hexagonal).
 */
class Turno
{
    private ?int $id;
    private int $sucursalId;
    private int $barmanId;
    private TipoTurno $tipoTurno;
    private EstadoTurno $estado;
    private DateTimeImmutable $fechaApertura;
    private ?DateTimeImmutable $fechaCierre;
    private int $totalTransformacionesNetas;
    private float $totalComisionBruta;
    private float $totalSancionDescontada;
    private float $totalComisionNetaPagada;
    private ?string $codigoReciboCobro;
    private ?DateTimeImmutable $fechaCobro;

    public function __construct(
        ?int $id,
        int $sucursalId,
        int $barmanId,
        TipoTurno $tipoTurno,
        EstadoTurno $estado = EstadoTurno::ABIERTO,
        ?DateTimeImmutable $fechaApertura = null,
        ?DateTimeImmutable $fechaCierre = null,
        int $totalTransformacionesNetas = 0,
        float $totalComisionBruta = 0.00,
        float $totalSancionDescontada = 0.00,
        float $totalComisionNetaPagada = 0.00,
        ?string $codigoReciboCobro = null,
        ?DateTimeImmutable $fechaCobro = null
    ) {
        $this->id = $id;
        $this->sucursalId = $sucursalId;
        $this->barmanId = $barmanId;
        $this->tipoTurno = $tipoTurno;
        $this->estado = $estado;
        $this->fechaApertura = $fechaApertura ?? new DateTimeImmutable();
        $this->fechaCierre = $fechaCierre;
        $this->totalTransformacionesNetas = $totalTransformacionesNetas;
        $this->totalComisionBruta = round($totalComisionBruta, 2);
        $this->totalSancionDescontada = round($totalSancionDescontada, 2);
        $this->totalComisionNetaPagada = round($totalComisionNetaPagada, 2);
        $this->codigoReciboCobro = $codigoReciboCobro;
        $this->fechaCobro = $fechaCobro;
    }

    public function id(): ?int { return $this->id; }
    public function sucursalId(): int { return $this->sucursalId; }
    public function barmanId(): int { return $this->barmanId; }
    public function tipoTurno(): TipoTurno { return $this->tipoTurno; }
    public function estado(): EstadoTurno { return $this->estado; }
    public function fechaApertura(): DateTimeImmutable { return $this->fechaApertura; }
    public function fechaCierre(): ?DateTimeImmutable { return $this->fechaCierre; }
    public function totalTransformacionesNetas(): int { return $this->totalTransformacionesNetas; }
    public function totalComisionBruta(): float { return $this->totalComisionBruta; }
    public function totalSancionDescontada(): float { return $this->totalSancionDescontada; }
    public function totalComisionNetaPagada(): float { return $this->totalComisionNetaPagada; }
    public function codigoReciboCobro(): ?string { return $this->codigoReciboCobro; }
    public function fechaCobro(): ?DateTimeImmutable { return $this->fechaCobro; }

    public function estaAbierto(): bool
    {
        return $this->estado === EstadoTurno::ABIERTO;
    }

    public function estaCobrado(): bool
    {
        return $this->estado === EstadoTurno::COBRADO;
    }

    /**
     * Agrega el resultado de una transformación al consolidado del turno.
     * Cumple con Principio Constitucional II: solo productos netos transformados devengan comisión.
     */
    public function agregarTransformacion(int $unidadesNetas, float $comisionGenerada): void
    {
        if ($this->estado !== EstadoTurno::ABIERTO) {
            throw new DomainException("No se pueden registrar transformaciones en un turno que no esté abierto.");
        }

        $this->totalTransformacionesNetas += $unidadesNetas;
        $this->totalComisionBruta = round($this->totalComisionBruta + $comisionGenerada, 2);
    }

    /**
     * Aplica el cobro ante cajera para Turno Día: congela montos y sella con código único.
     */
    public function sellarCobro(string $codigoRecibo, float $saldoDeudorADescontar): void
    {
        if ($this->estado !== EstadoTurno::ABIERTO) {
            throw new DomainException("El turno ya ha sido cobrado o no se encuentra en estado abierto.");
        }

        // El descuento de sanción no puede superar la comisión bruta generada
        $descuentoEfectivo = min($this->totalComisionBruta, max(0.00, $saldoDeudorADescontar));
        $netoPagar = round($this->totalComisionBruta - $descuentoEfectivo, 2);

        $this->totalSancionDescontada = $descuentoEfectivo;
        $this->totalComisionNetaPagada = $netoPagar;
        $this->codigoReciboCobro = $codigoRecibo;
        $this->fechaCobro = new DateTimeImmutable();
        $this->estado = EstadoTurno::COBRADO;
    }

    /**
     * Cierre definitivo de la jornada de 12 horas.
     */
    public function cerrar(): void
    {
        if ($this->estado === EstadoTurno::CERRADO || $this->estado === EstadoTurno::AUDITADO) {
            throw new DomainException("El turno ya se encuentra cerrado.");
        }

        $this->fechaCierre = new DateTimeImmutable();
        $this->estado = EstadoTurno::CERRADO;
    }
}
