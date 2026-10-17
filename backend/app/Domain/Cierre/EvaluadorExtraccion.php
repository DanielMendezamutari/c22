<?php

namespace App\Domain\Cierre;

/**
 * Mide qué tan bien leyó la IA un documento, contra la respuesta correcta leída por una persona (spec 005 T010).
 * Solo se evalúan los campos presentes en lo esperado: lo que la persona no pudo leer con seguridad no se mide.
 */
final class EvaluadorExtraccion
{
    /**
     * @param array $esperados  [['tipo' => ..., '<tipo>' => campos]]
     * @param array $extraidos  documentos devueltos por el extractor
     * @return array{tipos_esperados:int, tipos_encontrados:int, campos:int, campos_ok:int, errores:string[]}
     */
    public function evaluar(array $esperados, array $extraidos): array
    {
        $resultado = ['tipos_esperados' => 0, 'tipos_encontrados' => 0, 'campos' => 0, 'campos_ok' => 0, 'errores' => []];
        $disponibles = $extraidos;

        foreach ($esperados as $esperado) {
            $tipo = $esperado['tipo'];
            $resultado['tipos_esperados']++;

            $indice = null;
            foreach ($disponibles as $i => $doc) {
                if (($doc['tipo'] ?? null) === $tipo) {
                    $indice = $i;
                    break;
                }
            }

            $campos = $esperado[$tipo] ?? [];
            if ($indice === null) {
                $tiposLeidos = implode(', ', array_map(fn ($d) => $d['tipo'] ?? '?', $extraidos)) ?: 'ninguno';
                $resultado['errores'][] = "Falta documento '{$tipo}' (la IA leyó: {$tiposLeidos})";
                $resultado['campos'] += $this->contarCampos($campos);

                continue;
            }

            $resultado['tipos_encontrados']++;
            $leido = $disponibles[$indice][$tipo] ?? [];
            unset($disponibles[$indice]);
            $this->comparar($campos, is_array($leido) ? $leido : [], $tipo, $resultado);
        }

        return $resultado;
    }

    private function comparar(array $esperado, array $leido, string $ruta, array &$r): void
    {
        foreach ($esperado as $clave => $valor) {
            $rutaCampo = "{$ruta}.{$clave}";
            $valorLeido = $leido[$clave] ?? null;

            if (is_array($valor)) {
                $this->comparar($valor, is_array($valorLeido) ? $valorLeido : [], $rutaCampo, $r);

                continue;
            }

            $r['campos']++;
            if ($this->igual($valor, $valorLeido, (string) $clave)) {
                $r['campos_ok']++;
            } else {
                $r['errores'][] = "{$rutaCampo}: esperado " . json_encode($valor, JSON_UNESCAPED_UNICODE) . ', leído ' . json_encode($valorLeido, JSON_UNESCAPED_UNICODE);
            }
        }
    }

    private function igual(mixed $esperado, mixed $leido, string $clave): bool
    {
        if (is_int($esperado) || is_float($esperado)) {
            return is_numeric($leido) && Dinero::centavos((float) $esperado) === Dinero::centavos((float) $leido);
        }
        if ($leido === null || $leido === '') {
            return false;
        }

        $e = NombrePersona::normalizar((string) $esperado);
        $l = NombrePersona::normalizar((string) $leido);
        if (in_array($clave, ['servidor', 'motivo', 'destino_titular', 'origen_titular'], true)) {
            return str_contains($l, $e) || str_contains($e, $l);
        }
        if (in_array($clave, ['encargada', 'nombre', 'cargo'], true)) {
            return NombrePersona::mismaPersona($e, $l);
        }
        if ($clave === 'destino_cuenta' || $clave === 'origen_cuenta') {
            // Las apps enmascaran la cuenta ("131****961"): basta con que coincidan los dígitos visibles
            $ve = preg_replace('/\D/', '', (string) $esperado);
            $vl = preg_replace('/\D/', '', (string) $leido);

            return $vl !== '' && (str_contains($ve, $vl) || str_contains($vl, $ve) || (substr($ve, 0, 3) === substr($vl, 0, 3) && substr($ve, -3) === substr($vl, -3)));
        }

        return $e === $l;
    }

    private function contarCampos(array $campos): int
    {
        $n = 0;
        foreach ($campos as $v) {
            $n += is_array($v) ? $this->contarCampos($v) : 1;
        }

        return $n;
    }
}
