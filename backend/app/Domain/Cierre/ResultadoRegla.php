<?php

namespace App\Domain\Cierre;

/**
 * Resultado de una verificación. Los montos van en centavos para comparar sin error de punto flotante.
 */
final class ResultadoRegla
{
    public const CUMPLE = 'cumple';
    public const NO_CUMPLE = 'no_cumple';
    public const SIN_VERIFICAR = 'sin_verificar';
    public const PENDIENTE_GLOBAL = 'pendiente_global';

    /** Compara lo declarado contra evidencia independiente del dinero (sobre, comprobantes, POS, cuenta destino). */
    public const DINERO = 'dinero';

    /** Hay faltante cuando lo comprobado es MENOR que lo esperado (sobre con menos efectivo, ventas bajo el POS). */
    public const FALTA_SI_MENOR = 'menor';
    /** Hay faltante cuando lo comprobado es MAYOR que lo que corresponde (se pagó de más del fondo). */
    public const FALTA_SI_MAYOR = 'mayor';
    public const CUADRE = 'cuadre';

    public function __construct(
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly string $estado,
        public readonly string $categoria = self::CUADRE,
        public readonly ?int $esperadoCentavos = null,
        public readonly ?int $declaradoCentavos = null,
        public readonly ?string $detalle = null,
        public readonly string $faltaSi = self::FALTA_SI_MENOR,
    ) {
    }

    public static function comparar(string $codigo, string $descripcion, ?float $esperado, ?float $declarado, string $categoria = self::CUADRE, ?string $detalle = null, string $faltaSi = self::FALTA_SI_MENOR): self
    {
        if ($esperado === null || $declarado === null) {
            return new self($codigo, $descripcion, self::SIN_VERIFICAR, $categoria, Dinero::centavos($esperado), Dinero::centavos($declarado), $detalle ?? 'Falta un dato para verificar', $faltaSi);
        }

        $e = Dinero::centavos($esperado);
        $d = Dinero::centavos($declarado);

        return new self($codigo, $descripcion, $e === $d ? self::CUMPLE : self::NO_CUMPLE, $categoria, $e, $d, $detalle, $faltaSi);
    }

    /**
     * Solo es faltante una diferencia contra evidencia del dinero y en la dirección de la pérdida.
     * Un sobrante o una planilla que no cuadra consigo misma se revisan, pero no acusan a nadie.
     */
    public function esFaltante(): bool
    {
        if ($this->estado !== self::NO_CUMPLE || $this->categoria !== self::DINERO) {
            return false;
        }
        $diferencia = $this->diferencia();
        if ($diferencia === null) {
            return true; // sin montos: p. ej. transferencia a una cuenta que no es la oficial
        }

        return $this->faltaSi === self::FALTA_SI_MAYOR ? $diferencia > 0 : $diferencia < 0;
    }

    public function diferencia(): ?float
    {
        return $this->esperadoCentavos === null || $this->declaradoCentavos === null
            ? null
            : ($this->declaradoCentavos - $this->esperadoCentavos) / 100;
    }

    public function bloquea(): bool
    {
        return $this->estado === self::NO_CUMPLE || $this->estado === self::SIN_VERIFICAR;
    }

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'categoria' => $this->categoria,
            'esperado' => $this->esperadoCentavos === null ? null : $this->esperadoCentavos / 100,
            'declarado' => $this->declaradoCentavos === null ? null : $this->declaradoCentavos / 100,
            'diferencia' => $this->diferencia(),
            'faltante' => $this->esFaltante(),
            'detalle' => $this->detalle,
        ];
    }
}
