<?php

namespace App\Domain\Cierre;

final class ResultadoConciliacion
{
    public const CONFORME = 'CONFORME';
    /** La caja cuadra, pero hay pagos que solo se verifican cuando cierran todas las casas. Nunca es CONFORME. */
    public const CUADRA_PENDIENTE = 'CUADRA_PENDIENTE';
    public const REVISAR = 'REVISAR';
    public const FALTANTE = 'FALTANTE';

    /**
     * @param ResultadoRegla[] $reglas
     * @param string[] $alertas Puntos de auditoría que no bloquean (permitidos pero a revisar).
     * @param string[] $documentosFaltantes
     */
    public function __construct(
        public readonly array $reglas,
        public readonly array $alertas,
        public readonly array $documentosFaltantes,
    ) {
    }

    public function estado(): string
    {
        foreach ($this->reglas as $r) {
            if ($r->esFaltante()) {
                return self::FALTANTE;
            }
        }
        foreach ($this->reglas as $r) {
            if ($r->bloquea()) {
                return self::REVISAR;
            }
        }
        if ($this->documentosFaltantes !== []) {
            return self::REVISAR;
        }
        foreach ($this->reglas as $r) {
            if ($r->estado === ResultadoRegla::PENDIENTE_GLOBAL) {
                return self::CUADRA_PENDIENTE;
            }
        }

        return self::CONFORME;
    }

    /**
     * Reemplaza las reglas pendientes (garzones de día, encargada) por el resultado de LiquidacionGlobalTurno
     * correspondiente a esta casa.
     *
     * @param ResultadoRegla[] $globales
     */
    public function conVerificacionGlobal(array $globales): self
    {
        $reglas = array_values(array_filter($this->reglas, fn (ResultadoRegla $r) => $r->estado !== ResultadoRegla::PENDIENTE_GLOBAL));

        return new self(array_merge($reglas, $globales), $this->alertas, $this->documentosFaltantes);
    }

    public function regla(string $codigo): ?ResultadoRegla
    {
        foreach ($this->reglas as $r) {
            if ($r->codigo === $codigo) {
                return $r;
            }
        }

        return null;
    }

    /** @return ResultadoRegla[] */
    public function reglasCon(string $codigo): array
    {
        return array_values(array_filter($this->reglas, fn (ResultadoRegla $r) => $r->codigo === $codigo));
    }

    /** @return ResultadoRegla[] */
    public function conProblemas(): array
    {
        return array_values(array_filter($this->reglas, fn (ResultadoRegla $r) => $r->bloquea()));
    }

    public function toArray(): array
    {
        return [
            'estado' => $this->estado(),
            'reglas_cumplidas' => count(array_filter($this->reglas, fn ($r) => $r->estado === ResultadoRegla::CUMPLE)),
            'reglas_evaluadas' => count($this->reglas),
            'reglas' => array_map(fn ($r) => $r->toArray(), $this->reglas),
            'alertas' => $this->alertas,
            'documentos_faltantes' => $this->documentosFaltantes,
        ];
    }
}
