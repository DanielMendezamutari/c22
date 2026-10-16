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

    /** Una diferencia en esta categoría significa dinero que falta o sobra. */
    public const DINERO = 'dinero';
    public const CUADRE = 'cuadre';

    public function __construct(
        public readonly string $codigo,
        public readonly string $descripcion,
        public readonly string $estado,
        public readonly string $categoria = self::CUADRE,
        public readonly ?int $esperadoCentavos = null,
        public readonly ?int $declaradoCentavos = null,
        public readonly ?string $detalle = null,
    ) {
    }

    public static function comparar(string $codigo, string $descripcion, ?float $esperado, ?float $declarado, string $categoria = self::CUADRE, ?string $detalle = null): self
    {
        if ($esperado === null || $declarado === null) {
            return new self($codigo, $descripcion, self::SIN_VERIFICAR, $categoria, Dinero::centavos($esperado), Dinero::centavos($declarado), $detalle ?? 'Falta un dato para verificar');
        }

        $e = Dinero::centavos($esperado);
        $d = Dinero::centavos($declarado);

        return new self($codigo, $descripcion, $e === $d ? self::CUMPLE : self::NO_CUMPLE, $categoria, $e, $d, $detalle);
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
            'detalle' => $this->detalle,
        ];
    }
}
