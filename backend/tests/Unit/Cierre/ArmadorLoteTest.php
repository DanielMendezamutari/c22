<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\ArmadorLote;
use App\Domain\Cierre\ConciliadorCierre;
use App\Domain\Cierre\ParserTextoCierre;
use App\Domain\Cierre\ReglasPago;
use PHPUnit\Framework\TestCase;

class ArmadorLoteTest extends TestCase
{
    public function test_arma_todas_las_claves_del_lote_y_conserva_las_evidencias(): void
    {
        $fanny = CasosDorados::fanny();
        $ajeno = ['monto' => 54, 'destino_cuenta' => '555000222', 'destino_titular' => 'Proveedor'];
        $recibo = ['concepto' => 'Hielo', 'monto' => 20];
        $texto = "Venta total:1520\nQr:113";
        $lote = (new ArmadorLote(new ReglasPago(cuentasQrOficiales: ['1310000961'])))->armar([
            $this->documento('planilla_ventas', $fanny['planilla']),
            $this->documento('pago_chicas', ['filas' => $fanny['pago_chicas']]),
            $this->documento('reporte_pos', [
                'total_cobros' => 1520, 'ventas_efectivo' => 1000, 'qr' => 520,
                'categorias' => [
                    ['nombre' => 'BEBIDA CHICAS', 'monto' => 480],
                    ['nombre' => 'CAJERA', 'monto' => 1000],
                ],
                'items' => [['producto' => '168 - COMISION LIMPIEZA', 'cantidad' => 2]],
            ]),
            $this->documento('ticket_chicas', ['chicas' => [
                ['nombre' => 'FANNY', 'bebidas' => [
                    ['producto' => 'Bebida', 'precio' => 80, 'cantidad' => 1],
                    ['producto' => 'Otra bebida', 'precio' => 80, 'cantidad' => 2],
                    ['producto' => 'Especial', 'precio' => 100.5, 'cantidad' => 1],
                ]],
                ['nombre' => 'FANNY', 'bebidas' => [['precio' => 80, 'cantidad' => 1]]],
            ]]),
            $this->documento('sobre', ['monto' => 90]),
            $this->documento('comprobante_transferencia', [
                'monto' => 113, 'destino_cuenta' => '131****961', 'destino_titular' => 'Titular',
            ]),
            $this->documento('comprobante_transferencia', $ajeno),
            $this->documento('recibo_gasto', $recibo),
            $this->documento('control_shampagne', ['filas' => [
                ['nombre' => 'Fiorella', 'total' => 500, 'medio' => 'qr'],
            ]]),
        ], $texto);

        $this->assertSame($fanny['planilla'], $lote['planilla']);
        $this->assertSame($fanny['pago_chicas'], $lote['pago_chicas']);
        $this->assertSame([
            'total_cobros' => 1520, 'efectivo' => 1000, 'qr' => 520,
            'bebida_chicas' => 480, 'shampagne' => 1000, 'limpieza' => 20.0,
            'chicas' => ['FANNY' => [80 => 4, '100.5' => 1]],
        ], $lote['pos']);
        $this->assertSame(['monto' => 90], $lote['sobre']);
        $this->assertSame([
            ['monto' => 113, 'cuenta_destino' => '131****961', 'titular' => 'Titular'],
        ], $lote['comprobantes_qr']);
        $this->assertSame([$ajeno, $recibo], $lote['evidencia_egresos']);
        $this->assertSame([['nombre' => 'Fiorella', 'total' => 500]], $lote['control_shampagne']);
        $this->assertSame((new ParserTextoCierre())->parsear($texto), $lote['texto']);
        $this->assertArrayNotHasKey('alertas_armado', $lote);
    }

    public function test_el_lote_extraido_de_fanny_conserva_los_estados_del_caso_dorado(): void
    {
        $fanny = CasosDorados::fanny();
        $reglas = new ReglasPago(cuentasQrOficiales: [CasosDorados::CUENTA_OFICIAL]);
        $lote = (new ArmadorLote($reglas))->armar([
            $this->documento('planilla_ventas', $fanny['planilla']),
            $this->documento('pago_chicas', ['filas' => $fanny['pago_chicas']]),
            $this->documento('ticket_chicas', ['chicas' => [
                ['nombre' => 'FANNY', 'bebidas' => [['precio' => 80, 'cantidad' => 1]]],
                ['nombre' => 'FIORELLA', 'bebidas' => [['precio' => 80, 'cantidad' => 5]]],
            ]]),
            $this->documento('reporte_pos', [
                'total_cobros' => 1500, 'ventas_efectivo' => 1000, 'qr' => 500,
                'categorias' => [
                    ['nombre' => 'BEBIDA CHICAS', 'monto' => 480],
                    ['nombre' => 'CAJERA', 'monto' => 1000],
                ],
                'items' => [],
            ]),
            $this->documento('sobre', ['monto' => 90]),
            $this->documento('comprobante_transferencia', [
                'monto' => 113, 'destino_cuenta' => CasosDorados::CUENTA_OFICIAL, 'destino_titular' => 'DUEÑO',
            ]),
        ], "Casa 22vip\nVenta total:1520\nQr:113\nLink:0\nEfectivo:90\nFanny:169");

        $conciliador = new ConciliadorCierre($reglas);
        $esperado = $conciliador->conciliar($fanny);
        $resultado = $conciliador->conciliar($lote);
        foreach (['ING', 'EGR', 'SAL', 'SOB', 'POS', 'COM', 'CHI', 'LIM', 'CHP', 'QRC', 'QRD', 'TXT'] as $codigo) {
            $this->assertNotNull($resultado->regla($codigo), $codigo);
            $this->assertSame($esperado->regla($codigo)->estado, $resultado->regla($codigo)->estado, $codigo);
        }
        $this->assertSame($esperado->estado(), $resultado->estado());
    }

    public function test_dos_planillas_distintas_alertan_y_gana_la_mayor_confianza(): void
    {
        $baja = $this->documento('planilla_ventas', ['total_ingresos' => 100], 0.6);
        $alta = $this->documento('planilla_ventas', ['total_ingresos' => 200], 0.95);
        foreach ([[$baja, $alta], [$alta, $baja]] as $documentos) {
            $lote = (new ArmadorLote())->armar($documentos);
            $this->assertSame(['total_ingresos' => 200], $lote['planilla']);
            $this->assertCount(1, $lote['alertas_armado']);
            $this->assertStringStartsWith('Dos planillas distintas en el mismo cierre: ', $lote['alertas_armado'][0]);
        }
        $lote = (new ArmadorLote())->armar([
            $alta, $this->documento('planilla_ventas', ['total_ingresos' => 200.0]),
        ]);
        $this->assertArrayNotHasKey('alertas_armado', $lote);
    }

    public function test_sin_planilla_no_inventa_datos_y_el_texto_es_opcional(): void
    {
        $armador = new ArmadorLote();
        $this->assertSame(['planilla' => []], $armador->armar([]));
        $lote = $armador->armar([$this->documento('reporte_pos', [])]);
        $this->assertSame([], $lote['planilla']);
        $this->assertSame([
            'total_cobros' => null, 'efectivo' => null, 'qr' => null,
            'bebida_chicas' => null, 'shampagne' => null, 'limpieza' => 0,
        ], $lote['pos']);
        $this->assertArrayNotHasKey('texto', $lote);
        $this->assertSame((new ParserTextoCierre())->parsear(''), $armador->armar([], '')['texto']);
    }

    public function test_precio_null_se_ignora_con_alerta_y_cantidad_null_no_se_inventa(): void
    {
        $lote = (new ArmadorLote())->armar([$this->documento('ticket_chicas', ['chicas' => [
            ['nombre' => 'Ana', 'bebidas' => [
                ['precio' => null, 'cantidad' => 2, 'producto' => 'Ilegible'],
                ['precio' => 80, 'cantidad' => 1],
                ['precio' => 80, 'cantidad' => null],
            ]],
        ]])]);
        $this->assertSame(['Ana' => [80 => null]], $lote['pos']['chicas']);
        $this->assertCount(1, $lote['alertas_armado']);
        $this->assertStringContainsString('sin precio', $lote['alertas_armado'][0]);
    }

    public function test_sobre_gana_por_instante_y_no_por_confianza_ni_orden(): void
    {
        $anterior = $this->documento('sobre', ['monto' => 80], 1.0);
        $anterior['creado'] = '2026-10-17T12:00:00+00:00';
        $reciente = $this->documento('sobre', ['monto' => 90], 0.6);
        $reciente['creado'] = '2026-10-17T10:00:00-04:00';
        $sinFecha = $this->documento('sobre', ['monto' => 100]);
        foreach ([[$anterior, $reciente, $sinFecha], [$sinFecha, $reciente, $anterior]] as $documentos) {
            $this->assertSame(['monto' => 90], (new ArmadorLote())->armar($documentos)['sobre']);
        }
    }

    public function test_confianza_selecciona_documentos_y_normaliza_categorias_e_items(): void
    {
        $lote = (new ArmadorLote(new ReglasPago(limpiezaPorChica: 15)))->armar([
            $this->documento('reporte_pos', ['total_cobros' => 9999], 0.5),
            $this->documento('reporte_pos', [
                'total_cobros' => 100,
                'categorias' => [
                    ['nombre' => 'Bébida chícas', 'monto' => 80],
                    ['nombre' => 'CÁJERA', 'monto' => 20],
                    ['nombre' => 'OTRA CAJERA', 'monto' => 999],
                ],
                'items' => [['producto' => '168 - comisión limpieza', 'cantidad' => 2]],
            ], 0.95),
            $this->documento('pago_chicas', ['filas' => [['nombre' => 'Ana', 'total' => 40]]], 0.95),
            $this->documento('pago_chicas', ['filas' => []], 0.5),
        ]);
        $this->assertSame(100, $lote['pos']['total_cobros']);
        $this->assertSame(80, $lote['pos']['bebida_chicas']);
        $this->assertSame(20, $lote['pos']['shampagne']);
        $this->assertSame(30.0, $lote['pos']['limpieza']);
        $this->assertSame([['nombre' => 'Ana', 'total' => 40]], $lote['pago_chicas']);

        $lote = (new ArmadorLote())->armar([$this->documento('reporte_pos', [
            'categorias' => [['nombre' => 'CAJERA EXTRA', 'monto' => 100]],
            'items' => [['producto' => 'COMISION LIMPIEZA', 'cantidad' => null]],
        ])]);
        $this->assertNull($lote['pos']['shampagne']);
        $this->assertNull($lote['pos']['limpieza']);
    }

    public function test_cuentas_oficiales_por_digitos_y_mascara_conservan_todos_los_comprobantes(): void
    {
        $cuentas = ['131-0000-961', '131****961', '131****960', '131961', null];
        $documentos = array_map(fn ($cuenta) => $this->documento('comprobante_transferencia', [
            'monto' => 10, 'destino_cuenta' => $cuenta,
        ]), $cuentas);
        $lote = (new ArmadorLote(new ReglasPago(cuentasQrOficiales: ['131 0000 961'])))->armar($documentos);
        $this->assertCount(2, $lote['comprobantes_qr']);
        $this->assertCount(3, $lote['evidencia_egresos']);
        $this->assertNull($lote['comprobantes_qr'][0]['titular']);
        $sinConfigurar = (new ArmadorLote())->armar($documentos);
        $this->assertArrayNotHasKey('comprobantes_qr', $sinConfigurar);
        $this->assertCount(5, $sinConfigurar['evidencia_egresos']);
    }

    private function documento(string $tipo, array $datos, float $confianza = 0.9): array
    {
        return ['tipo' => $tipo, 'datos' => $datos, 'confianza' => $confianza];
    }
}
