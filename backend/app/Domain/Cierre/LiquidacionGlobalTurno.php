<?php

namespace App\Domain\Cierre;

/**
 * Pagos que dependen de las ventas de todas las casas en un mismo turno:
 *  - Garzones de día: 5% de las ventas de bebidas de todas las casas, repartido entre todos los garzones de día.
 *  - Encargadas: base del turno + 3% de las ventas de todas las casas repartido entre las encargadas.
 *
 * Los pagos se hacen en Bs enteros: a cada persona se le acepta el esperado exacto redondeado hacia abajo o
 * hacia arriba, y aparte se exige que la suma pagada no supere el total que corresponde redondeado hacia arriba
 * una sola vez (ej.: 4 encargadas de 202,75 → 203 + 203 + 203 + 202 = 811, no 4 × 203 = 812).
 * Así el redondeo individual no puede ocultar dinero de más.
 */
final class LiquidacionGlobalTurno
{
    public function __construct(private readonly ReglasPago $reglas = new ReglasPago())
    {
    }

    /**
     * @param array<int, array{casa: string, planilla: array}> $cierres Cierres del mismo día y turno.
     * @return ResultadoRegla[]
     */
    public function verificar(array $cierres, string $turno): array
    {
        $casas = array_map(fn ($c) => NombrePersona::normalizar((string) ($c['casa'] ?? '')), $cierres);
        if (count($casas) !== count(array_unique($casas)) || in_array('', $casas, true)) {
            return [new ResultadoRegla('GLB', 'Un cierre por casa en el turno', ResultadoRegla::NO_CUMPLE, ResultadoRegla::CUADRE, null, null, 'Hay cierres repetidos o sin casa: ' . implode(', ', array_column($cierres, 'casa')))];
        }
        if (count($casas) < $this->reglas->casasPorTurno) {
            return [new ResultadoRegla('GLB', 'Pagos entre casas (garzones de día y encargadas)', ResultadoRegla::PENDIENTE_GLOBAL, ResultadoRegla::CUADRE, null, null, 'Cerraron ' . count($casas) . " de {$this->reglas->casasPorTurno} casas")];
        }

        return array_merge(
            $turno === 'dia' ? $this->garzonesDia($cierres) : [],
            $this->encargadas($cierres, $turno),
        );
    }

    /**
     * Reglas de una casa en particular, para ResultadoConciliacion::conVerificacionGlobal().
     *
     * @param ResultadoRegla[] $resultados
     * @return ResultadoRegla[]
     */
    public static function deCasa(array $resultados, string $casa): array
    {
        return array_values(array_filter($resultados, fn (ResultadoRegla $r) => $r->codigo === 'GLB' || $r->codigo === 'GDT' || $r->codigo === 'ENT' || str_contains($r->descripcion, "({$casa})")));
    }

    private function garzonesDia(array $cierres): array
    {
        $ventasBebidas = Dinero::suma(array_map(fn ($c) => $c['planilla']['ingresos']['ventas_bebidas'] ?? null, $cierres));
        $garzones = [];
        foreach ($cierres as $c) {
            foreach ($c['planilla']['garzones'] ?? [] as $g) {
                $garzones[] = ['casa' => $c['casa']] + $g;
            }
        }
        if ($garzones === []) {
            return [];
        }
        if ($ventasBebidas === null) {
            return [new ResultadoRegla('GDT', 'Fondo de garzones de día', ResultadoRegla::SIN_VERIFICAR, ResultadoRegla::CUADRE, null, null, 'Falta la venta de bebidas de alguna casa')];
        }

        $fondo = Dinero::porcentaje($ventasBebidas, $this->reglas->pctGarzones);
        $detalle = "{$this->reglas->pctGarzones}% de Bs " . Dinero::formato($ventasBebidas) . ' = ' . Dinero::formato($fondo) . ' entre ' . count($garzones) . ' garzones';

        return $this->repartir('GDI', 'GDT', 'Garzón de día', 'Fondo de garzones de día', $garzones, fn ($g) => $g['pagado'] ?? null, $fondo, 0.0, $detalle);
    }

    private function encargadas(array $cierres, string $turno): array
    {
        $ventas = Dinero::suma(array_map(fn ($c) => Dinero::suma([
            $c['planilla']['ingresos']['ventas_bebidas'] ?? null,
            $c['planilla']['ingresos']['covers'] ?? 0,
            $c['planilla']['ingresos']['shampagne'] ?? 0,
        ]), $cierres));

        $encargadas = array_values(array_filter(array_map(fn ($c) => ['casa' => $c['casa'], 'nombre' => $c['planilla']['encargada'] ?? null, 'personal' => $c['planilla']['personal'] ?? []], $cierres), fn ($e) => $e['nombre']));
        if ($encargadas === []) {
            return [];
        }
        if ($ventas === null) {
            return [new ResultadoRegla('ENT', 'Fondo de encargadas', ResultadoRegla::SIN_VERIFICAR, ResultadoRegla::CUADRE, null, null, 'Falta la venta de alguna casa')];
        }

        $base = $this->reglas->baseEncargada($turno);
        $comision = Dinero::porcentaje($ventas, $this->reglas->pctEncargadas);
        $detalle = "base {$base} + {$this->reglas->pctEncargadas}% de Bs " . Dinero::formato($ventas) . ' entre ' . count($encargadas) . ' encargadas'
            . ($this->reglas->baseEncargadaProvisional ? ' (base provisional)' : '')
            . ($this->reglas->bonosEncargada === [] ? '; bonos no configurados' : '');

        // El pago de la encargada es el renglón de cargo "cajera/caja" a su nombre
        $pagado = function ($e) {
            $pagos = array_filter($e['personal'], fn ($x) => NombrePersona::mismaPersona($x['nombre'], $e['nombre'])
                && str_starts_with(NombrePersona::normalizar((string) ($x['cargo'] ?? '')), 'ca'));

            return $pagos === [] ? null : Dinero::suma(array_map(fn ($x) => $x['pagado'] ?? null, $pagos));
        };

        return $this->repartir('ENC', 'ENT', 'Encargada', 'Fondo de encargadas', $encargadas, $pagado, $comision, $base, $detalle);
    }

    /**
     * @param callable(array): ?float $pagado
     */
    private function repartir(string $codigo, string $codigoTotal, string $rol, string $descTotal, array $personas, callable $pagado, float $fondo, float $basePorPersona, string $detalle): array
    {
        $n = count($personas);
        $esperado = $basePorPersona + $fondo / $n;
        $resultados = [];
        $totalPagado = 0.0;
        $faltaDato = false;

        foreach ($personas as $p) {
            $monto = $pagado($p);
            $faltaDato = $faltaDato || $monto === null;
            $totalPagado += (float) $monto;
            $aceptable = $monto !== null && (Dinero::centavos($monto) === Dinero::centavos(floor($esperado)) || Dinero::centavos($monto) === Dinero::centavos(ceil($esperado)) || Dinero::centavos($monto) === Dinero::centavos($esperado));

            $resultados[] = new ResultadoRegla(
                $codigo,
                "{$rol} {$p['nombre']} ({$p['casa']})",
                $monto === null ? ResultadoRegla::SIN_VERIFICAR : ($aceptable ? ResultadoRegla::CUMPLE : ResultadoRegla::NO_CUMPLE),
                ResultadoRegla::CUADRE,
                Dinero::centavos(round($esperado, 2)),
                Dinero::centavos($monto),
                $detalle,
            );
        }

        $totalEsperado = $basePorPersona * $n + $fondo;
        $maximo = ceil(round($totalEsperado, 2));
        $resultados[] = new ResultadoRegla(
            $codigoTotal,
            "{$descTotal}: lo pagado no supera lo que corresponde",
            $faltaDato ? ResultadoRegla::SIN_VERIFICAR : (Dinero::centavos($totalPagado) <= Dinero::centavos($maximo) ? ResultadoRegla::CUMPLE : ResultadoRegla::NO_CUMPLE),
            ResultadoRegla::DINERO,
            Dinero::centavos($totalEsperado),
            Dinero::centavos($totalPagado),
            "Máximo aceptable por redondeo a Bs enteros: " . Dinero::formato($maximo),
        );

        return $resultados;
    }
}
