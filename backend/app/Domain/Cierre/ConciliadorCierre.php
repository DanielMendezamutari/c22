<?php

namespace App\Domain\Cierre;

/**
 * Verifica un lote de cierre de una encargada con reglas deterministas (spec 005).
 * La IA solo lee los documentos; aquí se decide si cuadran. Ver research.md §2 y §6.
 *
 * Principio: un dato no leído (null) nunca se trata como cero; la regla queda "sin verificar" y bloquea CONFORME.
 *
 * Estructura del lote (todas las claves son opcionales salvo 'planilla'):
 *  planilla: encargada, fecha, turno ('dia'|'noche'),
 *            ingresos[caja_chica, ventas_bebidas, covers, shampagne, comision_limpieza, ...otros conceptos],
 *            otros_ingresos[[concepto, monto]], total_ingresos,
 *            egresos[pago_chicas, pago_garzones, pago_personal, ...], total_egresos,
 *            total, qr, linkser, efectivo, efectivo_en_sobre,
 *            garzones[[nombre, total_vendido, pagado]], personal[[nombre, cargo, pagado]]
 *  pago_chicas: [[nombre, manillas, bot, shampagne, limpieza, total]]
 *  control_shampagne: [[nombre, total, pagado]]
 *  pos: total_cobros, efectivo, qr, limpieza, bebida_chicas, shampagne, chicas{nombre: {precio: cantidad}}
 *  sobre: {monto}
 *  comprobantes_qr: [[monto, cuenta_destino, titular]]
 *  texto: salida de ParserTextoCierre
 */
final class ConciliadorCierre
{
    public function __construct(private readonly ReglasPago $reglasPago = new ReglasPago())
    {
    }

    public function conciliar(array $lote): ResultadoConciliacion
    {
        $p = $lote['planilla'] ?? [];
        $ingresos = $p['ingresos'] ?? [];
        $egresos = $p['egresos'] ?? [];
        $otrosIngresos = $p['otros_ingresos'] ?? [];
        $alertas = [];

        $faltantes = [];
        foreach (['pos' => 'Reporte de ventas del POS', 'pago_chicas' => 'Hoja de pago de chicas', 'sobre' => 'Sobre de efectivo'] as $clave => $nombre) {
            if (empty($lote[$clave])) {
                $faltantes[] = $nombre;
            }
        }
        if (($p['qr'] ?? 0) > 0 && empty($lote['comprobantes_qr'])) {
            $faltantes[] = 'Comprobante de transferencia del QR';
        }

        // ING: la caja chica es el efectivo cobrado según POS, informativo; no suma (caso Fanny: 1520, no 2520)
        $sumaIngresos = Dinero::suma(array_merge(
            array_values(array_diff_key($ingresos, ['caja_chica' => 0])),
            array_column($otrosIngresos, 'monto'),
        ));
        $reglas = [
            ResultadoRegla::comparar('ING', 'Suma de ingresos = total de ingresos', $sumaIngresos, $p['total_ingresos'] ?? null),
            ResultadoRegla::comparar('EGR', 'Suma de egresos = total de egresos', $egresos === [] ? null : Dinero::suma(array_values($egresos)), $p['total_egresos'] ?? null),
        ];

        $saldo = isset($p['total_ingresos'], $p['total_egresos']) ? $p['total_ingresos'] - $p['total_egresos'] : null;
        $entregado = Dinero::suma([$p['qr'] ?? null, $p['linkser'] ?? 0, $p['efectivo'] ?? null]);
        // Cuadre interno de la planilla: si no cuadra puede ser error de lectura o de anotación, no prueba un faltante
        $reglas[] = ResultadoRegla::comparar('SAL', 'Ingresos − egresos = QR + Linkser + efectivo', $saldo, $entregado);

        // El sobre solo se acredita con el documento del sobre, nunca con lo que dice la propia planilla
        $reglas[] = ResultadoRegla::comparar('SOB', 'Efectivo declarado = efectivo en el sobre', $p['efectivo'] ?? null, $lote['sobre']['monto'] ?? null, ResultadoRegla::DINERO);

        $reglas = array_merge(
            $reglas,
            $this->reglasPersonal($p, $egresos),
            $this->reglasPos($ingresos, $egresos, $lote['pos'] ?? null),
            $this->reglasChicas($lote, $ingresos, $egresos, $alertas),
            $this->reglasShampagne($lote['control_shampagne'] ?? [], $lote['pago_chicas'] ?? [], $ingresos),
            $this->reglasQr($p, $lote['comprobantes_qr'] ?? []),
            $this->reglasTexto($p, $lote['texto'] ?? null),
        );

        $alertas = array_merge($alertas, $this->alertas($p, $lote, $otrosIngresos));

        return new ResultadoConciliacion($reglas, $alertas, $faltantes);
    }

    private function reglasPersonal(array $p, array $egresos): array
    {
        $garzones = $p['garzones'] ?? [];
        $reglas = [
            ResultadoRegla::comparar('GAR', 'Pagos a garzones = egreso de garzones', Dinero::suma(array_map(fn ($g) => $g['pagado'] ?? null, $garzones)), $egresos['pago_garzones'] ?? ($garzones === [] ? 0.0 : null)),
            ResultadoRegla::comparar('PER', 'Pagos al personal = egreso de personal', Dinero::suma(array_map(fn ($x) => $x['pagado'] ?? null, $p['personal'] ?? [])), $egresos['pago_personal'] ?? (empty($p['personal']) ? 0.0 : null)),
        ];

        if (($p['turno'] ?? null) === 'noche') {
            foreach ($garzones as $g) {
                $venta = $g['total_vendido'] ?? null;
                $reglas[] = ResultadoRegla::comparar(
                    'GNO',
                    "Garzón {$g['nombre']}: {$this->reglasPago->pctGarzones}% de su venta",
                    $venta === null ? null : Dinero::porcentaje((float) $venta, $this->reglasPago->pctGarzones),
                    $g['pagado'] ?? null,
                );
            }
        } elseif ($garzones !== []) {
            $reglas[] = $this->pendiente('GDI', "Garzones de día: {$this->reglasPago->pctGarzones}% de las ventas de bebidas de las {$this->reglasPago->casasPorTurno} casas, repartido entre todos");
        }

        if (!empty($p['encargada'])) {
            $reglas[] = $this->pendiente('ENC', "Pago de la encargada {$p['encargada']}: base + {$this->reglasPago->pctEncargadas}% de las ventas de las {$this->reglasPago->casasPorTurno} casas");
        }

        return $reglas;
    }

    private function reglasPos(array $ingresos, array $egresos, ?array $pos): array
    {
        if (!$pos) {
            return [];
        }

        // El POS a veces incluye la comisión de limpieza (Sisiela) y a veces no (Fanny): se compara sin ella
        $ventasPlanilla = Dinero::suma([$ingresos['ventas_bebidas'] ?? null, $ingresos['covers'] ?? 0, $ingresos['shampagne'] ?? 0]);
        $ventasPos = isset($pos['total_cobros']) ? $pos['total_cobros'] - ($pos['limpieza'] ?? 0) : null;

        $bebidaChicas = $pos['bebida_chicas'] ?? null;
        $shampagne = $pos['shampagne'] ?? $ingresos['shampagne'] ?? null;
        $comision = $bebidaChicas === null || $shampagne === null ? null
            : Dinero::porcentaje((float) $bebidaChicas, $this->reglasPago->pctBebidaChica) + Dinero::porcentaje((float) $shampagne, $this->reglasPago->pctShampagne);

        return [
            ResultadoRegla::comparar('POS', 'Ventas de la planilla (bebidas + covers + shampagne) = POS', $ventasPos, $ventasPlanilla, ResultadoRegla::DINERO),
            ResultadoRegla::comparar('COM', "Pago de chicas = {$this->reglasPago->pctBebidaChica}% bebidas de chica + {$this->reglasPago->pctShampagne}% shampagne (POS)", $comision, $egresos['pago_chicas'] ?? null),
        ];
    }

    private function reglasChicas(array $lote, array $ingresos, array $egresos, array &$alertas): array
    {
        $hoja = $lote['pago_chicas'] ?? [];
        if ($hoja === []) {
            return [];
        }

        $reglas = [ResultadoRegla::comparar('CHI', 'Hoja de pago de chicas = egreso de chicas', Dinero::suma(array_map(fn ($f) => $f['total'] ?? null, $hoja)), $egresos['pago_chicas'] ?? null)];

        // Limpieza: una vez por chica (aunque figure en varias filas) cuando genera más del umbral
        $totales = array_map(fn ($f) => $f['total'] ?? null, $hoja);
        if (in_array(null, $totales, true)) {
            $reglas[] = ResultadoRegla::comparar('LIM', 'Limpieza por chica', null, $ingresos['comision_limpieza'] ?? 0.0);
        } else {
            $porChica = NombrePersona::agrupar(array_map(fn ($f) => [$f['nombre'], (float) $f['total']], $hoja));
            $conLimpieza = array_filter($porChica, fn ($total) => $total > $this->reglasPago->limpiezaUmbral);
            $reglas[] = ResultadoRegla::comparar(
                'LIM',
                "Limpieza: {$this->reglasPago->limpiezaPorChica} Bs por chica que genera más de {$this->reglasPago->limpiezaUmbral} Bs",
                count($conLimpieza) * $this->reglasPago->limpiezaPorChica,
                $ingresos['comision_limpieza'] ?? 0.0,
                ResultadoRegla::CUADRE,
                count($conLimpieza) . ' chica(s): ' . implode(', ', array_keys($conLimpieza)),
            );
        }

        if (empty($lote['pos'])) {
            return $reglas;
        }
        if (!isset($lote['pos']['chicas']) || !is_array($lote['pos']['chicas'])) {
            $reglas[] = new ResultadoRegla('CHP', 'Comisión de bebidas de cada chica = sus bebidas en el POS', ResultadoRegla::SIN_VERIFICAR, ResultadoRegla::CUADRE, null, null, 'Falta el detalle de bebidas por chica del POS');

            return $reglas;
        }

        $reglas[] = $this->reglaPorChica($hoja, $lote['pos']['chicas'], $alertas);

        return $reglas;
    }

    private function reglaPorChica(array $hoja, array $posChicas, array &$alertas): ResultadoRegla
    {
        $hojaBebidas = NombrePersona::agrupar(array_map(fn ($f) => [$f['nombre'], (float) ($f['manillas'] ?? 0) + (float) ($f['bot'] ?? 0)], $hoja));
        $esperadoPos = [];
        foreach ($posChicas as $nombre => $bebidas) {
            $monto = 0.0;
            foreach ((array) $bebidas as $precio => $cantidad) {
                $monto += Dinero::porcentaje((float) $precio * (float) $cantidad, $this->reglasPago->pctBebidaChica);
            }
            $esperadoPos[(string) $nombre] = $monto;
        }

        // Emparejar: primero coincidencia segura; luego aproximada única (queda como alerta para revisión humana)
        $pares = [];
        $aproximados = [];
        $hojaLibre = array_keys($hojaBebidas);
        foreach (array_keys($esperadoPos) as $nombrePos) {
            $enHoja = NombrePersona::buscar($nombrePos, $hojaLibre);
            if ($enHoja !== null) {
                $pares[$nombrePos] = $enHoja;
                $hojaLibre = array_values(array_diff($hojaLibre, [$enHoja]));
            }
        }
        foreach (array_keys($esperadoPos) as $nombrePos) {
            if (!isset($pares[$nombrePos]) && ($aprox = NombrePersona::buscarAproximado($nombrePos, $hojaLibre)) !== null) {
                $pares[$nombrePos] = $aprox;
                $hojaLibre = array_values(array_diff($hojaLibre, [$aprox]));
                $aproximados[] = "\"{$aprox}\" = \"{$nombrePos}\"";
                $alertas[] = "Nombre aproximado: \"{$aprox}\" (hoja) se tomó como \"{$nombrePos}\" (POS). Confirmar que es la misma persona.";
            }
        }

        $diferencias = [];
        foreach ($esperadoPos as $nombrePos => $esperado) {
            $pagado = isset($pares[$nombrePos]) ? $hojaBebidas[$pares[$nombrePos]] : 0.0;
            if (Dinero::centavos($pagado) !== Dinero::centavos($esperado)) {
                $diferencias[] = "{$nombrePos}: POS " . Dinero::formato($esperado) . ' vs hoja ' . Dinero::formato($pagado);
            }
        }
        foreach ($hojaLibre as $nombreHoja) {
            if ($hojaBebidas[$nombreHoja] > 0) {
                $diferencias[] = "{$nombreHoja}: cobra " . Dinero::formato($hojaBebidas[$nombreHoja]) . ' sin bebidas en el POS';
            }
        }

        // Con nombres emparejados por aproximación no se da por verificado: es dinero de una persona concreta
        // ("Ana" podría no ser "Anabel"); queda sin verificar hasta que una persona lo confirme.
        $estado = match (true) {
            $diferencias !== [] => ResultadoRegla::NO_CUMPLE,
            $aproximados !== [] => ResultadoRegla::SIN_VERIFICAR,
            default => ResultadoRegla::CUMPLE,
        };
        $detalle = match ($estado) {
            ResultadoRegla::NO_CUMPLE => implode('; ', $diferencias),
            ResultadoRegla::SIN_VERIFICAR => 'Montos coinciden; confirmar que son la misma persona: ' . implode(', ', $aproximados),
            default => count($esperadoPos) . ' chicas verificadas',
        };

        return new ResultadoRegla('CHP', 'Comisión de bebidas de cada chica = sus bebidas en el POS', $estado, ResultadoRegla::CUADRE, null, null, $detalle);
    }

    private function reglasShampagne(array $control, array $hoja, array $ingresos): array
    {
        if ($control === []) {
            return [];
        }

        $reglas = [ResultadoRegla::comparar('SHP', 'Control de shampagne = ingreso de shampagne', Dinero::suma(array_map(fn ($f) => $f['total'] ?? null, $control)), $ingresos['shampagne'] ?? null)];
        foreach ($control as $fila) {
            $reglas[] = ResultadoRegla::comparar(
                'SHC',
                "Shampagne {$fila['nombre']}: {$this->reglasPago->pctShampagne}%",
                isset($fila['total']) ? Dinero::porcentaje((float) $fila['total'], $this->reglasPago->pctShampagne) : null,
                $fila['pagado'] ?? null,
            );
        }

        // Cruce persona por persona: lo pagado en la hoja de chicas = lo que le corresponde según el control
        $controlPorChica = NombrePersona::agrupar(array_map(fn ($f) => [$f['nombre'], (float) ($f['pagado'] ?? 0)], $control));
        $hojaPorChica = NombrePersona::agrupar(array_map(fn ($f) => [$f['nombre'], (float) ($f['shampagne'] ?? 0)], $hoja));
        $diferencias = [];
        foreach ($controlPorChica as $nombre => $pagado) {
            $enHoja = NombrePersona::buscar($nombre, array_keys($hojaPorChica));
            $enHojaMonto = $enHoja === null ? 0.0 : $hojaPorChica[$enHoja];
            if (Dinero::centavos($enHojaMonto) !== Dinero::centavos($pagado)) {
                $diferencias[] = "{$nombre}: control " . Dinero::formato($pagado) . ' vs hoja ' . Dinero::formato($enHojaMonto);
            }
        }
        foreach ($hojaPorChica as $nombre => $monto) {
            if ($monto > 0 && NombrePersona::buscar($nombre, array_keys($controlPorChica)) === null) {
                $diferencias[] = "{$nombre}: cobra shampagne " . Dinero::formato($monto) . ' sin figurar en el control';
            }
        }
        $reglas[] = new ResultadoRegla('SHX', 'Shampagne pagado a cada chica = control de shampagne', $diferencias === [] ? ResultadoRegla::CUMPLE : ResultadoRegla::NO_CUMPLE, ResultadoRegla::CUADRE, null, null, $diferencias === [] ? null : implode('; ', $diferencias));

        return $reglas;
    }

    private function reglasQr(array $p, array $comprobantes): array
    {
        if ($comprobantes === []) {
            return [];
        }

        $reglas = [ResultadoRegla::comparar('QRC', 'Comprobantes de transferencia = QR declarado', $p['qr'] ?? null, Dinero::suma(array_map(fn ($c) => $c['monto'] ?? null, $comprobantes)), ResultadoRegla::DINERO)];

        if ($this->reglasPago->cuentasQrOficiales === []) {
            $reglas[] = new ResultadoRegla('QRD', 'El QR se transfirió a la cuenta oficial', ResultadoRegla::SIN_VERIFICAR, ResultadoRegla::DINERO, null, null, 'Cuenta oficial no configurada');

            return $reglas;
        }

        $ajenas = array_filter($comprobantes, fn ($c) => !in_array(preg_replace('/\D/', '', (string) ($c['cuenta_destino'] ?? '')), $this->reglasPago->cuentasQrOficiales, true));
        $reglas[] = new ResultadoRegla(
            'QRD',
            'El QR se transfirió a la cuenta oficial',
            $ajenas === [] ? ResultadoRegla::CUMPLE : ResultadoRegla::NO_CUMPLE,
            ResultadoRegla::DINERO,
            null,
            null,
            $ajenas === [] ? null : 'Transferido a: ' . implode(', ', array_map(fn ($c) => ($c['titular'] ?? '?') . ' (' . ($c['cuenta_destino'] ?? '?') . ')', $ajenas)),
        );

        return $reglas;
    }

    private function reglasTexto(array $p, ?array $texto): array
    {
        if (!$texto) {
            return [];
        }

        $reglas = [];
        foreach (['venta_total' => 'total_ingresos', 'qr' => 'qr', 'linkser' => 'linkser', 'efectivo' => 'efectivo'] as $claveTexto => $clavePlanilla) {
            if (($texto[$claveTexto] ?? null) !== null) {
                $reglas[] = ResultadoRegla::comparar('TXT', "Texto de WhatsApp ({$claveTexto}) = planilla", $p[$clavePlanilla] ?? ($clavePlanilla === 'linkser' ? 0.0 : null), $texto[$claveTexto]);
            }
        }
        foreach ($texto['pagos_personal'] ?? [] as $nombre => $monto) {
            $pagos = array_filter($p['personal'] ?? [], fn ($x) => NombrePersona::mismaPersona($x['nombre'], $nombre) && Dinero::centavos((float) ($x['pagado'] ?? -1)) === Dinero::centavos((float) $monto));
            $reglas[] = new ResultadoRegla('TXT', "Texto de WhatsApp: pago a {$nombre} figura en la planilla", $pagos === [] ? ResultadoRegla::NO_CUMPLE : ResultadoRegla::CUMPLE, ResultadoRegla::CUADRE, Dinero::centavos((float) $monto));
        }

        return $reglas;
    }

    private function alertas(array $p, array $lote, array $otrosIngresos): array
    {
        $alertas = [];
        $encargada = $p['encargada'] ?? null;
        $turno = $p['turno'] ?? 'dia';

        foreach ($otrosIngresos as $o) {
            $alertas[] = "Venta fuera del POS: \"{$o['concepto']}\" Bs " . Dinero::formato((float) ($o['monto'] ?? 0)) . '.';
        }

        // Personal (no chicas) que cobra comisión de chica: permitido, pero se marca
        $personal = array_merge(
            $encargada ? [$encargada] : [],
            array_column($p['garzones'] ?? [], 'nombre'),
            array_column($p['personal'] ?? [], 'nombre'),
        );
        $cobranComoChica = array_merge(array_column($lote['pago_chicas'] ?? [], 'nombre'), array_keys($lote['pos']['chicas'] ?? []));
        $marcados = [];
        foreach ($cobranComoChica as $nombre) {
            $coincide = NombrePersona::buscar((string) $nombre, $personal);
            if ($coincide !== null && NombrePersona::buscar($coincide, $marcados) === null) {
                $marcados[] = $coincide;
            }
        }
        foreach ($marcados as $nombre) {
            $alertas[] = $nombre === $encargada
                ? "La encargada {$nombre} cobra comisión de chica."
                : "{$nombre} es personal del turno y cobra comisión de chica.";
        }

        if ($encargada) {
            $pagosEncargada = array_filter($p['personal'] ?? [], fn ($x) => NombrePersona::mismaPersona($x['nombre'], $encargada));
            if (count($pagosEncargada) > 1) {
                $alertas[] = "La encargada {$encargada} se paga por " . count($pagosEncargada) . ' conceptos: ' . implode(', ', array_map(fn ($x) => ($x['cargo'] ?? '?') . ' ' . Dinero::formato((float) ($x['pagado'] ?? 0)), $pagosEncargada)) . '.';
            }
        }

        if ($this->reglasPago->baseEncargadaProvisional) {
            $base = Dinero::formato($this->reglasPago->baseEncargada($turno));
            $alertas[] = "Base de encargada ({$turno}: {$base} Bs) provisional, pendiente de confirmación de administración.";
        }
        if ($this->reglasPago->bonosEncargada === []) {
            $alertas[] = 'Bonos de encargada no configurados: no se verifican.';
        }

        return $alertas;
    }

    private function pendiente(string $codigo, string $descripcion): ResultadoRegla
    {
        return new ResultadoRegla($codigo, $descripcion, ResultadoRegla::PENDIENTE_GLOBAL, ResultadoRegla::CUADRE, null, null, 'Se verifica cuando cierren todas las casas del turno');
    }
}
