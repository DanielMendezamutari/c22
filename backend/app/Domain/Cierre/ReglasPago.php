<?php

namespace App\Domain\Cierre;

/**
 * Reglas de comisiones y pagos, iguales en las 4 casas (confirmadas por el dueño 2026-10-16).
 * Los valores marcados como provisionales generan alerta en el informe hasta que administración los confirme.
 */
final class ReglasPago
{
    /**
     * @param string[] $cuentasQrOficiales Números de cuenta autorizados para recibir el QR.
     * @param array<int, array{desde: float, bono: float}> $bonosEncargada Vacío = bonos aún no configurados.
     */
    public function __construct(
        public readonly float $pctBebidaChica = 50.0,
        public readonly float $pctShampagne = 60.0,
        public readonly float $limpiezaPorChica = 10.0,
        public readonly float $limpiezaUmbral = 100.0, // se cobra cuando la chica genera MÁS de este monto
        public readonly float $pctGarzones = 5.0,
        public readonly float $pctEncargadas = 3.0,
        public readonly float $baseEncargadaDia = 100.0,
        public readonly float $baseEncargadaNoche = 50.0,
        public readonly bool $baseEncargadaProvisional = true,
        public readonly array $bonosEncargada = [],
        public readonly array $cuentasQrOficiales = [],
        public readonly int $casasPorTurno = 4,
    ) {
    }

    public function baseEncargada(string $turno): float
    {
        return $turno === 'noche' ? $this->baseEncargadaNoche : $this->baseEncargadaDia;
    }
}
