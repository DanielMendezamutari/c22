<?php

namespace App\Domain\Cierre;

final class ParserTextoCierre
{
    public function parsear(string $texto): array
    {
        $resultado = [
            'casa' => null,
            'venta_total' => null,
            'qr' => null,
            'linkser' => null,
            'efectivo' => null,
            'pagos_personal' => [],
            'lineas_no_reconocidas' => [],
        ];

        foreach (preg_split('/\R/u', $texto) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            $partes = [];
            $explicito = preg_match('/^([^:=]+?)\s*[:=]\s*(.+)$/u', $linea, $partes) === 1;
            if (!$explicito) {
                preg_match('/^(.+?)\s+((?:[-−+]?\s*)?(?:bs\.?\s*)?[-−+]?\s*\d.*)$/iu', $linea, $partes);
            }

            if ($partes !== []) {
                $nombre = trim($partes[1]);
                $clave = $this->clave($nombre);
                $monto = $this->monto($partes[2]);

                if ($monto !== null && ($explicito || $clave !== null)) {
                    if ($clave !== null) {
                        $resultado[$clave] = $monto;
                    } else {
                        $resultado['pagos_personal'][$nombre] = $monto;
                    }
                    continue;
                }
            }

            if ($resultado['casa'] === null) {
                $resultado['casa'] = $linea;
            } else {
                $resultado['lineas_no_reconocidas'][] = $linea;
            }
        }

        return $resultado;
    }

    private function clave(string $nombre): ?string
    {
        $nombre = strtolower(strtr($nombre, [
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]));
        $nombre = preg_replace('/[\s.\p{M}]+/u', '', $nombre);

        return match ($nombre) {
            'ventatotal', 'ventas', 'totalventa', 'venta' => 'venta_total',
            'qr', 'transferencia' => 'qr',
            'link', 'linkser', 'tarjeta' => 'linkser',
            'efectivo', 'sobre', 'efectivoensobre' => 'efectivo',
            default => null,
        };
    }

    private function monto(string $texto): ?float
    {
        if (!preg_match('/^\s*[-−+]?\s*(?:bs\.?\s*)?[-−+]?\s*(\d[\d.,]*)(?:\.-)?\s*(?:bs\.?)?\s*$/iu', $texto, $partes)) {
            return null;
        }

        $numero = $partes[1];
        // El último separador es decimal solo cuando lo siguen uno o dos dígitos.
        if (preg_match('/^(.*)[.,](\d{1,2})$/', $numero, $decimal)) {
            $entero = $decimal[1];
            $fraccion = '.'.$decimal[2];
        } else {
            $entero = $numero;
            $fraccion = '';
        }

        if (!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+|\d{1,3}(?:,\d{3})+)$/', $entero)) {
            return null;
        }

        return (float) (str_replace(['.', ','], '', $entero).$fraccion);
    }
}
