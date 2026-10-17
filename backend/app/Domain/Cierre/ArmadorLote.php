<?php

namespace App\Domain\Cierre;

final class ArmadorLote
{
    public function __construct(private readonly ReglasPago $reglas = new ReglasPago())
    {
    }

    /**
     * @param array<int, array{tipo: string, datos: array, confianza: float, creado?: string}> $documentos
     */
    public function armar(array $documentos, ?string $textoMensajes = null): array
    {
        $lote = ['planilla' => []];
        $elegidos = [];
        $totalesPlanilla = [];

        foreach ($documentos as $documento) {
            $tipo = $documento['tipo'];
            $datos = $documento['datos'];

            // Las transferencias y los gastos son evidencias independientes: se conservan todos.
            if ($tipo === 'comprobante_transferencia') {
                if (CuentaBancaria::coincide($datos['destino_cuenta'] ?? null, $this->reglas->cuentasQrOficiales)) {
                    $lote['comprobantes_qr'][] = [
                        'monto' => $datos['monto'] ?? null,
                        'cuenta_destino' => $datos['destino_cuenta'],
                        'titular' => $datos['destino_titular'] ?? null,
                    ];
                } else {
                    $lote['evidencia_egresos'][] = $datos;
                }
                continue;
            }
            if ($tipo === 'recibo_gasto') {
                $lote['evidencia_egresos'][] = $datos;
                continue;
            }

            if ($tipo === 'planilla_ventas' && isset($datos['total_ingresos'])) {
                $totalesPlanilla[] = Dinero::centavos((float) $datos['total_ingresos']);
            }

            $actual = $elegidos[$tipo] ?? null;
            if ($actual === null || ($tipo === 'sobre'
                ? $this->instante($documento) > $this->instante($actual)
                : $documento['confianza'] > $actual['confianza'])) {
                $elegidos[$tipo] = $documento;
            }
        }

        if (count(array_unique($totalesPlanilla)) > 1) {
            $lote['alertas_armado'][] = 'Dos planillas distintas en el mismo cierre: total_ingresos diferentes; se usa la de mayor confianza.';
        }

        foreach ($elegidos as $tipo => $documento) {
            $datos = $documento['datos'];
            switch ($tipo) {
                case 'planilla_ventas':
                    $lote['planilla'] = $datos;
                    break;
                case 'pago_chicas':
                    $lote['pago_chicas'] = $datos['filas'] ?? [];
                    break;
                case 'control_shampagne':
                    $lote['control_shampagne'] = array_map(
                        fn (array $fila) => array_intersect_key($fila, array_flip(['nombre', 'total'])),
                        $datos['filas'] ?? [],
                    );
                    break;
                case 'reporte_pos':
                    $lote['pos'] = array_merge($lote['pos'] ?? [], $this->armarPos($datos));
                    break;
                case 'ticket_chicas':
                    $chicas = [];
                    foreach ($datos['chicas'] ?? [] as $chica) {
                        foreach ($chica['bebidas'] ?? [] as $bebida) {
                            $producto = $bebida['producto'] ?? '';
                            if (!preg_match('/\b(?:chica|ch)\b/u', $this->normalizar($producto))) {
                                continue;
                            }
                            $precio = $bebida['precio'] ?? null;
                            if ($precio === null && preg_match('/(\d+(?:[.,]\d+)?)(?!.*\d)/u', $producto, $numero)) {
                                $precio = str_replace(',', '.', $numero[1]);
                            }
                            if ($precio === null) {
                                $lote['alertas_armado'][] = "Bebida sin precio en ticket de {$chica['nombre']}: ".($bebida['producto'] ?? 'producto ilegible').'.';
                                continue;
                            }
                            // PHP convierte claves float a enteros: usar string conserva precios con centavos.
                            $precio = (string) $precio;
                            $nombre = $chica['nombre'];
                            $cantidad = $bebida['cantidad'] ?? null;
                            if (!array_key_exists($precio, $chicas[$nombre] ?? [])) {
                                $chicas[$nombre][$precio] = $cantidad;
                            } elseif ($chicas[$nombre][$precio] === null || $cantidad === null) {
                                $chicas[$nombre][$precio] = null;
                            } else {
                                $chicas[$nombre][$precio] += $cantidad;
                            }
                        }
                    }
                    $lote['pos']['chicas'] = $chicas;
                    break;
                case 'sobre':
                    $lote['sobre'] = ['monto' => $datos['monto'] ?? null];
                    break;
            }
        }

        if ($textoMensajes !== null) {
            $lote['texto'] = (new ParserTextoCierre())->parsear($textoMensajes);
        }

        return $lote;
    }

    private function armarPos(array $datos): array
    {
        $pos = [
            'total_cobros' => $datos['total_cobros'] ?? null,
            'efectivo' => $datos['ventas_efectivo'] ?? null,
            'qr' => $datos['qr'] ?? null,
            'bebida_chicas' => null,
            'shampagne' => null,
            'limpieza' => 0,
        ];
        foreach ($datos['categorias'] ?? [] as $categoria) {
            $nombre = $this->normalizar($categoria['nombre']);
            if (str_contains($nombre, 'bebida chica')) {
                $pos['bebida_chicas'] = $categoria['monto'] ?? null;
            }
            if ($nombre === 'cajera') {
                $pos['shampagne'] = $categoria['monto'] ?? null;
            }
        }
        foreach ($datos['items'] ?? [] as $item) {
            if (str_contains($this->normalizar($item['producto']), 'comision limpieza')) {
                $pos['limpieza'] = isset($item['cantidad'])
                    ? $item['cantidad'] * $this->reglas->limpiezaPorChica : null;
                break;
            }
        }

        return $pos;
    }

    private function instante(array $documento): float
    {
        return isset($documento['creado'])
            ? (float) (new \DateTimeImmutable($documento['creado']))->format('U.u') : -INF;
    }

    private function normalizar(string $texto): string
    {
        return trim(strtolower(strtr($texto, [
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ])));
    }
}
