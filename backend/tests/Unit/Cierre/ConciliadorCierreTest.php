<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\ConciliadorCierre;
use App\Domain\Cierre\CuentaBancaria;
use App\Domain\Cierre\LiquidacionGlobalTurno;
use App\Domain\Cierre\NombrePersona;
use App\Domain\Cierre\ReglasPago;
use App\Domain\Cierre\ResultadoConciliacion;
use App\Domain\Cierre\ResultadoRegla;
use PHPUnit\Framework\TestCase;

/**
 * Spec 005 SC-001/SC-002: los cierres reales cuadran y cualquier alteración deja de hacerlo.
 * Incluye regresiones de la revisión cruzada de Codex (falsos "conforme").
 */
class ConciliadorCierreTest extends TestCase
{
    private ConciliadorCierre $conciliador;

    protected function setUp(): void
    {
        $this->conciliador = new ConciliadorCierre(new ReglasPago(cuentasQrOficiales: [CasosDorados::CUENTA_OFICIAL]));
    }

    public function test_cierre_real_de_fanny_cuadra_con_pagos_entre_casas_pendientes(): void
    {
        $r = $this->conciliador->conciliar(CasosDorados::fanny());

        $this->assertSame(ResultadoConciliacion::CUADRA_PENDIENTE, $r->estado(), $this->problemas($r));
        foreach (['ING', 'EGR', 'SAL', 'SOB', 'GAR', 'PER', 'POS', 'COM', 'CHI', 'LIM', 'CHP', 'QRC', 'QRD', 'TXT'] as $codigo) {
            $this->assertSame(ResultadoRegla::CUMPLE, $r->regla($codigo)?->estado, "Regla {$codigo}");
        }
        $this->assertSame(ResultadoRegla::PENDIENTE_GLOBAL, $r->regla('GDI')->estado);
        $this->assertSame(ResultadoRegla::PENDIENTE_GLOBAL, $r->regla('ENC')->estado);
    }

    public function test_cierre_real_de_sisiela_cuadra_pero_falta_el_comprobante_del_qr(): void
    {
        $r = $this->conciliador->conciliar(CasosDorados::sisiela());

        $this->assertSame(ResultadoConciliacion::REVISAR, $r->estado());
        $this->assertSame(['CHP'], array_map(fn ($x) => $x->codigo, $r->conProblemas()), $this->problemas($r));
        $this->assertSame(['Comprobante de transferencia del QR'], $r->documentosFaltantes);
        foreach (['ING', 'EGR', 'SAL', 'SOB', 'POS', 'COM', 'CHI', 'LIM', 'SHP', 'SHX', 'GNO'] as $codigo) {
            $this->assertSame(ResultadoRegla::CUMPLE, $r->regla($codigo)?->estado, "Regla {$codigo}");
        }
        // "Angy" (hoja) ~ "ANGIE" (POS): montos iguales, pero el nombre lo confirma una persona
        $this->assertSame(ResultadoRegla::SIN_VERIFICAR, $r->regla('CHP')->estado);
        $this->assertStringContainsString('"Angy" = "ANGIE"', $r->regla('CHP')->detalle);

        $lote = CasosDorados::sisiela();
        $lote['comprobantes_qr'] = [['monto' => 1266, 'cuenta_destino' => CasosDorados::CUENTA_OFICIAL]];
        $lote['pago_chicas'][0]['nombre'] = 'Angie'; // nombre confirmado por una persona
        $this->assertSame(ResultadoConciliacion::CUADRA_PENDIENTE, $this->conciliador->conciliar($lote)->estado());
    }

    public function test_alertas_del_caso_fanny(): void
    {
        $alertas = implode("\n", $this->conciliador->conciliar(CasosDorados::fanny())->alertas);

        $this->assertStringContainsString('La encargada Fanny Morón cobra comisión de chica', $alertas);
        $this->assertStringContainsString('se paga por 2 conceptos', $alertas);
        $this->assertStringContainsString('provisional, pendiente de confirmación de administración', $alertas);
    }

    public function test_alertas_del_caso_sisiela(): void
    {
        $alertas = implode("\n", $this->conciliador->conciliar(CasosDorados::sisiela())->alertas);

        $this->assertStringContainsString('Venta fuera del POS: "1 papa sin comandar"', $alertas);
        $this->assertStringContainsString('Edwin es personal del turno y cobra comisión de chica', $alertas);
        $this->assertStringContainsString('J. Pablo es personal del turno y cobra comisión de chica', $alertas);
        $this->assertStringContainsString('Nombre aproximado: "Angy" (hoja) se tomó como "ANGIE" (POS)', $alertas);
    }

    public function test_limpieza_se_cobra_solo_por_encima_de_100_y_una_vez_por_chica(): void
    {
        $r = $this->conciliador->conciliar(CasosDorados::sisiela())->regla('LIM');

        // Susana generó exactamente 100 (no paga); Luz figura en dos filas (paga una vez)
        $this->assertSame(ResultadoRegla::CUMPLE, $r->estado);
        $this->assertStringContainsString('6 chica(s)', $r->detalle);
        $this->assertStringNotContainsString('Susana', $r->detalle);
    }

    public function test_sobre_con_menos_efectivo_es_faltante_con_la_diferencia_exacta(): void
    {
        $lote = CasosDorados::sisiela();
        $lote['sobre']['monto'] = 2737;

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoConciliacion::FALTANTE, $r->estado());
        $this->assertSame(-100.0, $r->regla('SOB')->diferencia());
    }

    /**
     * @dataProvider alteraciones
     */
    public function test_ninguna_alteracion_puede_dar_conforme(callable $alterar): void
    {
        $lote = CasosDorados::fanny();
        $alterar($lote);

        $estado = $this->conciliador->conciliar($lote)->estado();

        $this->assertContains($estado, [ResultadoConciliacion::REVISAR, ResultadoConciliacion::FALTANTE]);
    }

    public static function alteraciones(): array
    {
        return [
            'total ingresos mal sumado' => [fn (&$l) => $l['planilla']['total_ingresos'] = 1530],
            'egreso inflado' => [fn (&$l) => $l['planilla']['egresos']['almuerzos'] = 190],
            'QR declarado menor' => [fn (&$l) => $l['planilla']['qr'] = 13],
            'ventas menores que el POS' => [fn (&$l) => $l['planilla']['ingresos']['ventas_bebidas'] = 400],
            'chica cobra de más' => [fn (&$l) => $l['pago_chicas'][0]['manillas'] = 240],
            'chica sin bebidas en POS' => [fn (&$l) => $l['pos']['chicas']['FIORELLA'] = [80 => 4]],
            'limpieza no cobrada' => [fn (&$l) => $l['planilla']['ingresos']['comision_limpieza'] = 10],
            'falta el POS' => [fn (&$l) => $l['pos'] = null],
            'falta la hoja de chicas' => [fn (&$l) => $l['pago_chicas'] = []],
            'efectivo no leído por la IA' => [fn (&$l) => $l['planilla']['efectivo'] = null],
            // Regresiones de la revisión de Codex
            'QR sin comprobante' => [fn (&$l) => $l['comprobantes_qr'] = []],
            'POS sin bebidas de chica ni detalle' => [function (&$l) { $l['pos']['bebida_chicas'] = null; $l['pos']['chicas'] = null; }],
            'sobre ilegible (no vale el de la planilla)' => [fn (&$l) => $l['sobre']['monto'] = null],
            'egreso ilegible tratado como cero' => [function (&$l) {
                $l['planilla']['egresos']['almuerzos'] = null;
                $l['planilla']['total_egresos'] = 1227;
                $l['planilla']['efectivo'] = 180;
                $l['sobre']['monto'] = 180;
            }],
            'nombre distinto en hoja y POS' => [function (&$l) {
                $l['pago_chicas'][0]['nombre'] = 'Mario Lopez';
                $l['pos']['chicas'] = ['FANNY' => [80 => 1], 'Maria Perez' => [80 => 5]];
            }],
        ];
    }

    public function test_un_pago_pendiente_nunca_es_conforme_aunque_este_inflado(): void
    {
        // Codex #3: garzón 127 → 227 con todo lo demás ajustado para cuadrar la caja
        $lote = CasosDorados::fanny();
        $lote['planilla']['garzones'][0]['pagado'] = 227;
        $lote['planilla']['egresos']['pago_garzones'] = 227;
        $lote['planilla']['total_egresos'] = 1417;
        $lote['planilla']['qr'] = 13;
        $lote['comprobantes_qr'][0]['monto'] = 13;
        $lote['texto']['qr'] = 13.0;

        $r = $this->conciliador->conciliar($lote);
        $this->assertSame(ResultadoConciliacion::CUADRA_PENDIENTE, $r->estado());

        $global = (new LiquidacionGlobalTurno())->verificar($this->cuatroCasasDia($lote['planilla']), 'dia');
        $final = $r->conVerificacionGlobal(LiquidacionGlobalTurno::deCasa($global, 'Casa 22 VIP'));
        $this->assertNotSame(ResultadoConciliacion::CONFORME, $final->estado());
        $this->assertSame(ResultadoRegla::NO_CUMPLE, $final->reglasCon('GDI')[0]->estado);
    }

    public function test_shampagne_no_puede_pasarse_de_una_chica_a_otra(): void
    {
        // Codex #4: Ruby 240 → 140 y Cloe 180 → 280 con el control intacto
        $lote = CasosDorados::sisiela();
        $lote['pago_chicas'][4]['shampagne'] = 140;
        $lote['pago_chicas'][4]['total'] = 460;
        $lote['pago_chicas'][10]['shampagne'] = 280;
        $lote['pago_chicas'][10]['total'] = 700;

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoRegla::NO_CUMPLE, $r->regla('SHX')->estado);
        $this->assertStringContainsString('Ruby', $r->regla('SHX')->detalle);
    }

    public function test_nombres_distintos_no_se_toman_como_la_misma_persona(): void
    {
        $this->assertFalse(NombrePersona::mismaPersona('Maria Perez', 'Mario Lopez'));
        $this->assertFalse(NombrePersona::mismaPersona('Juan Perez', 'Jose Perez'));
        $this->assertFalse(NombrePersona::parecido('Maria Perez', 'Mario Perez'));
        $this->assertTrue(NombrePersona::mismaPersona('J. Pablo', 'Juan Pablo Perez'));
        $this->assertTrue(NombrePersona::mismaPersona('Fanny', 'Fanny Morón'));
        $this->assertTrue(NombrePersona::parecido('Angy', 'ANGIE'));
        $this->assertTrue(NombrePersona::parecido('Nati', 'Natalia'));
        $this->assertTrue(NombrePersona::parecido('Abi', 'ABY'));
        $this->assertFalse(NombrePersona::parecido('Ana', 'Abi'));
        $this->assertFalse(NombrePersona::parecido('Luz', 'Lia'));
    }

    public function test_nombre_aproximado_nunca_da_cumple(): void
    {
        // Codex: "Ana" (hoja) y "Anabel" (POS) podían emparejarse y dar CUMPLE
        $lote = CasosDorados::fanny();
        $lote['pago_chicas'][0]['nombre'] = 'Fio';

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoRegla::SIN_VERIFICAR, $r->regla('CHP')->estado);
        $this->assertSame(ResultadoConciliacion::REVISAR, $r->estado());
    }

    public function test_inicial_con_apellido_mantiene_la_alerta_de_personal(): void
    {
        // Codex #8: agregar el apellido no debe ocultar que seguridad cobra comisión de chica
        $lote = CasosDorados::sisiela();
        $lote['pago_chicas'][7]['nombre'] = 'Juan Pablo Perez';
        $lote['pos']['chicas']['Juan Pablo Perez'] = $lote['pos']['chicas']['JUAN PABLO'];
        unset($lote['pos']['chicas']['JUAN PABLO']);

        $alertas = implode("\n", $this->conciliador->conciliar($lote)->alertas);

        $this->assertStringContainsString('J. Pablo es personal del turno y cobra comisión de chica', $alertas);
    }

    public function test_texto_de_whatsapp_distinto_de_la_planilla_se_marca(): void
    {
        $lote = CasosDorados::fanny();
        $lote['texto']['efectivo'] = 190.0;

        $this->assertSame(ResultadoConciliacion::REVISAR, $this->conciliador->conciliar($lote)->estado());
    }

    public function test_sobrante_en_el_sobre_se_revisa_pero_no_es_faltante(): void
    {
        // VIP noche 16-10: sobre 1580 con efectivo declarado 1569 (+11)
        $lote = CasosDorados::sisiela();
        $lote['sobre']['monto'] = 2848;

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoRegla::NO_CUMPLE, $r->regla('SOB')->estado);
        $this->assertFalse($r->regla('SOB')->esFaltante());
        $this->assertSame(ResultadoConciliacion::REVISAR, $r->estado());
    }

    public function test_planilla_que_no_cuadra_consigo_misma_se_revisa_pero_no_acusa(): void
    {
        // Corona noche 16-10: si la IA lee Linkser 0 en vez de 2638, la planilla deja de cuadrar consigo misma.
        // Eso puede ser un error de lectura: REVISAR, nunca FALTANTE.
        $lote = CasosDorados::sisiela();
        $lote['planilla']['qr'] = 0;
        $lote['comprobantes_qr'] = [['monto' => 1266, 'cuenta_destino' => CasosDorados::CUENTA_OFICIAL]];
        $lote['pago_chicas'][0]['nombre'] = 'Angie';

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoRegla::NO_CUMPLE, $r->regla('SAL')->estado);
        $this->assertSame(ResultadoConciliacion::REVISAR, $r->estado());
    }

    public function test_ventas_declaradas_bajo_el_pos_si_es_faltante(): void
    {
        $lote = CasosDorados::fanny();
        $lote['planilla']['ingresos']['ventas_bebidas'] = 400;
        $lote['planilla']['total_ingresos'] = 1420;
        // El defecto #3 exige una planilla internamente consistente para concluir faltante.
        $lote['planilla']['qr'] = 13;

        $r = $this->conciliador->conciliar($lote);

        $this->assertTrue($r->regla('POS')->esFaltante());
        $this->assertSame(ResultadoConciliacion::FALTANTE, $r->estado());
    }

    public function test_qr_transferido_a_otra_cuenta_es_faltante(): void
    {
        $lote = CasosDorados::fanny();
        $lote['comprobantes_qr'][0]['cuenta_destino'] = '400555870';

        $r = $this->conciliador->conciliar($lote);

        $this->assertSame(ResultadoConciliacion::FALTANTE, $r->estado());
        $this->assertSame(ResultadoRegla::NO_CUMPLE, $r->regla('QRD')->estado);
    }

    public function test_pagos_entre_casas_quedan_pendientes_hasta_que_cierren_todas(): void
    {
        $r = (new LiquidacionGlobalTurno())->verificar([['casa' => 'Casa 22 VIP', 'planilla' => CasosDorados::fanny()['planilla']]], 'dia');

        $this->assertSame(ResultadoRegla::PENDIENTE_GLOBAL, $r[0]->estado);
        $this->assertStringContainsString('1 de 4', $r[0]->detalle);
    }

    public function test_cuatro_copias_de_una_casa_no_cuentan_como_cuatro_casas(): void
    {
        // Codex #6
        $cierre = ['casa' => 'Casa22', 'planilla' => CasosDorados::fanny()['planilla']];

        $r = (new LiquidacionGlobalTurno())->verificar([$cierre, $cierre, $cierre, $cierre], 'dia');

        $this->assertCount(1, $r);
        $this->assertSame(ResultadoRegla::NO_CUMPLE, $r[0]->estado);
    }

    public function test_redondeo_no_permite_repartir_mas_que_el_fondo(): void
    {
        // Codex #7: 4 casas × 10 Bs de bebidas → fondo 2 Bs; pagar 1 Bs a cada uno de 4 garzones = 4 Bs
        $casa = fn (string $n) => ['casa' => $n, 'planilla' => ['encargada' => "E{$n}", 'ingresos' => ['ventas_bebidas' => 10], 'garzones' => [['nombre' => "G{$n}", 'pagado' => 1]], 'personal' => [['nombre' => "E{$n}", 'cargo' => 'Cajera', 'pagado' => 100]]]];

        $r = (new LiquidacionGlobalTurno())->verificar([$casa('A'), $casa('B'), $casa('C'), $casa('D')], 'dia');
        $fondo = array_values(array_filter($r, fn ($x) => $x->codigo === 'GDT'))[0];

        $this->assertSame(ResultadoRegla::NO_CUMPLE, $fondo->estado);
        $this->assertSame(ResultadoRegla::DINERO, $fondo->categoria);
    }

    public function test_garzones_de_dia_reparten_el_5_por_ciento_de_las_4_casas(): void
    {
        $r = (new LiquidacionGlobalTurno())->verificar($this->cuatroCasasDia(CasosDorados::fanny()['planilla']), 'dia');
        $garzones = array_values(array_filter($r, fn ($x) => $x->codigo === 'GDI'));
        $encargadas = array_values(array_filter($r, fn ($x) => $x->codigo === 'ENC'));

        // Bebidas: 500 + 4000 + 4200 + 4000 = 12.700 → 5% = 635 entre 5 garzones = 127 c/u (el pago de Cuevas es correcto)
        $this->assertCount(5, $garzones);
        foreach ($garzones as $g) {
            $this->assertSame(ResultadoRegla::CUMPLE, $g->estado, $g->descripcion);
        }

        // Encargadas: ventas 1.500 + 4.000 + 4.200 + 4.000 = 13.700 → 3% = 411 / 4 = 102,75 + base 100 = 202,75
        $this->assertSame(20275, $encargadas[0]->esperadoCentavos);
        $this->assertSame(ResultadoRegla::NO_CUMPLE, $encargadas[0]->estado); // Fanny se pagó 169
        $this->assertSame(ResultadoRegla::CUMPLE, $encargadas[1]->estado);   // 203 = 202,75 redondeado
    }

    public function test_cuentas_exactas_y_enmascaradas_no_confunden_destinos(): void
    {
        foreach (['131****961', '131xxx961', '131XXX961', '131 1250 961'] as $cuenta) {
            $this->assertTrue(CuentaBancaria::coincide($cuenta, ['1311250961']), $cuenta);
        }
        foreach ([null, '', '   ', '131961', '131****960', '13****961', '131****61', '9991250961'] as $cuenta) {
            $this->assertFalse(CuentaBancaria::coincide($cuenta, ['1311250961']));
        }
    }

    public function test_pos_suma_todos_los_conceptos_sin_otros_ingresos(): void
    {
        $lote = ['planilla' => [
            'ingresos' => ['ventas_bebidas' => 22030, 'covers' => 1080, 'shampagne' => 5850, 'chupete' => 1015, 'privados' => 0, 'chuflay' => 0],
            'otros_ingresos' => [['concepto' => 'Fuera del POS', 'monto' => 100]],
        ], 'pos' => ['total_cobros' => 30145, 'limpieza' => 180]];
        $r = $this->conciliador->conciliar($lote)->regla('POS');
        $this->assertSame(2997500, $r->declaradoCentavos);
        $this->assertSame(2996500, $r->esperadoCentavos);
        $this->assertSame(10.0, $r->diferencia());
        foreach (array_keys($lote['planilla']['ingresos']) as $campo) {
            $modificado = $lote;
            $modificado['planilla']['ingresos'][$campo] = null;
            $this->assertSame(ResultadoRegla::SIN_VERIFICAR, $this->conciliador->conciliar($modificado)->regla('POS')->estado, $campo);
        }
        $lote['planilla']['ingresos']['privados'] = 200;
        $lote['planilla']['ingresos']['chuflay'] = 300;
        $this->assertSame(3047500, $this->conciliador->conciliar($lote)->regla('POS')->declaradoCentavos);
    }

    public function test_faltante_exige_las_tres_reglas_internas_salvo_cuenta_ajena(): void
    {
        $faltante = ResultadoRegla::comparar('SOB', 'Sobre', 100, 50, ResultadoRegla::DINERO);
        foreach (['ING', 'EGR', 'SAL'] as $codigo) {
            foreach ([null, ResultadoRegla::NO_CUMPLE, ResultadoRegla::SIN_VERIFICAR] as $estado) {
                $reglas = [$faltante];
                foreach (['ING', 'EGR', 'SAL'] as $interna) {
                    if ($interna !== $codigo || $estado !== null) {
                        $reglas[] = new ResultadoRegla($interna, $interna, $interna === $codigo ? $estado : ResultadoRegla::CUMPLE);
                    }
                }
                $r = new ResultadoConciliacion($reglas, [], []);
                $this->assertSame(ResultadoConciliacion::REVISAR, $r->estado());
                $this->assertSame('La planilla leída no cuadra consigo misma: verificar la lectura antes de concluir un faltante', $r->toArray()['motivo']);
                $reglas[] = new ResultadoRegla('QRD', 'Cuenta ajena', ResultadoRegla::NO_CUMPLE, ResultadoRegla::DINERO);
                $r = new ResultadoConciliacion($reglas, [], []);
                $this->assertSame(ResultadoConciliacion::FALTANTE, $r->estado());
                $this->assertArrayNotHasKey('motivo', $r->toArray());
            }
        }
    }

    public function test_shampagne_sin_pagado_calcula_porcentaje_y_agrupa_personas(): void
    {
        $conciliador = new ConciliadorCierre(new ReglasPago(pctShampagne: 65));
        $lote = ['control_shampagne' => [
            ['nombre' => 'Ana', 'total' => 100],
            ['nombre' => 'Ana', 'total' => 200, 'pagado' => null],
            ['nombre' => 'Rosa', 'total' => 100, 'pagado' => 65],
        ], 'pago_chicas' => [
            ['nombre' => 'Ana', 'shampagne' => 130],
            ['nombre' => 'Ana', 'shampagne' => 65],
            ['nombre' => 'Rosa', 'shampagne' => 65],
        ]];
        $r = $conciliador->conciliar($lote);
        $this->assertCount(1, $r->reglasCon('SHC'));
        $this->assertSame(ResultadoRegla::CUMPLE, $r->regla('SHX')->estado);
        $lote['pago_chicas'][0]['shampagne'] = 120;
        $this->assertSame(ResultadoRegla::NO_CUMPLE, $conciliador->conciliar($lote)->regla('SHX')->estado);
        unset($lote['control_shampagne'][0]['total']);
        $this->assertSame(ResultadoRegla::SIN_VERIFICAR, $conciliador->conciliar($lote)->regla('SHX')->estado);
    }

    public function test_sin_efectivo_no_exige_sobre_pero_null_no_se_inventa(): void
    {
        foreach ([['efectivo' => 0], ['efectivo' => null, 'efectivo_en_sobre' => 0]] as $planilla) {
            $r = $this->conciliador->conciliar(['planilla' => $planilla]);
            $this->assertNotContains('Sobre de efectivo', $r->documentosFaltantes);
            $this->assertSame(ResultadoRegla::CUMPLE, $r->regla('SOB')->estado);
            $this->assertSame('Sin efectivo a entregar', $r->regla('SOB')->detalle);
        }
        foreach ([[], ['efectivo' => null], ['efectivo' => 10, 'efectivo_en_sobre' => 0]] as $planilla) {
            $r = $this->conciliador->conciliar(['planilla' => $planilla]);
            $this->assertContains('Sobre de efectivo', $r->documentosFaltantes);
            $this->assertSame(ResultadoRegla::SIN_VERIFICAR, $r->regla('SOB')->estado);
        }
    }

    private function cuatroCasasDia(array $planillaFanny): array
    {
        $casa = fn (string $nombre, float $bebidas, array $garzones, string $encargada) => [
            'casa' => $nombre,
            'planilla' => [
                'encargada' => $encargada,
                'ingresos' => ['ventas_bebidas' => $bebidas],
                'garzones' => array_map(fn ($g) => ['nombre' => $g, 'pagado' => 127], $garzones),
                'personal' => [['nombre' => $encargada, 'cargo' => 'Cajera', 'pagado' => 203]],
            ],
        ];

        return [
            ['casa' => 'Casa 22 VIP', 'planilla' => $planillaFanny],
            $casa('Casa22', 4000, ['Pedro'], 'Ana'),
            $casa('Corona', 4200, ['Juan'], 'Rosa'),
            $casa('Madan', 4000, ['Luis', 'Mario'], 'Eva'),
        ];
    }

    private function problemas(ResultadoConciliacion $r): string
    {
        return implode("\n", array_map(fn ($x) => "{$x->codigo} {$x->estado}: {$x->descripcion} " . json_encode($x->toArray()), $r->conProblemas()))
            . "\nFaltan: " . implode(', ', $r->documentosFaltantes);
    }
}
