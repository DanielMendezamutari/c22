<?php

namespace Tests\Unit\Cierre;

use App\Domain\Cierre\ParserTextoCierre;

/**
 * Cierres reales leídos a mano y verificados (specs/005-conciliacion-cierre-ia/research.md §1 y §6).
 * La cuenta bancaria es ficticia; las fotos originales viven fuera de git.
 */
final class CasosDorados
{
    public const CUENTA_OFICIAL = '9990001111';

    /** Casa 22 VIP · Día · miércoles 14-10-2026 · Fanny Morón. */
    public static function fanny(): array
    {
        return [
            'planilla' => [
                'encargada' => 'Fanny Morón', 'fecha' => '2026-10-14', 'turno' => 'dia',
                'ingresos' => ['caja_chica' => 1000, 'ventas_bebidas' => 500, 'shampagne' => 1000, 'comision_limpieza' => 20],
                'total_ingresos' => 1520,
                'egresos' => ['pago_chicas' => 840, 'pago_garzones' => 127, 'pago_personal' => 189, 'almuerzos' => 90, 'taxis_chicas' => 54, 'otros' => 17],
                'total_egresos' => 1317, 'total' => 203, 'linkser' => 0, 'qr' => 113, 'efectivo' => 90, 'efectivo_en_sobre' => 90,
                'garzones' => [['nombre' => 'Cuevas', 'total_vendido' => 300, 'pagado' => 127]],
                'personal' => [
                    ['nombre' => 'Fanny', 'cargo' => 'Limpieza', 'pagado' => 20],
                    ['nombre' => 'Fanny', 'cargo' => 'Caja', 'pagado' => 169],
                ],
            ],
            'pago_chicas' => [
                ['nombre' => 'Fiorella SM', 'manillas' => 200, 'bot' => 0, 'shampagne' => 300, 'limpieza' => 10, 'total' => 500],
                ['nombre' => 'Yubinka', 'manillas' => 0, 'bot' => 0, 'shampagne' => 300, 'limpieza' => 10, 'total' => 300],
                ['nombre' => 'Fanny', 'manillas' => 40, 'bot' => 0, 'shampagne' => 0, 'limpieza' => 0, 'total' => 40],
            ],
            'pos' => [
                'total_cobros' => 1500, 'efectivo' => 1000, 'qr' => 500, 'limpieza' => 0,
                'bebida_chicas' => 480, 'shampagne' => 1000,
                'chicas' => ['FANNY' => [80 => 1], 'FIORELLA' => [80 => 5]],
            ],
            'sobre' => ['monto' => 90],
            'comprobantes_qr' => [['monto' => 113, 'cuenta_destino' => self::CUENTA_OFICIAL, 'titular' => 'DUEÑO']],
            'texto' => (new ParserTextoCierre())->parsear("Casa 22vip\nVenta total:1520\nQr:113\nLink:0\nEfectivo:90\nFanny:169"),
        ];
    }

    /** Casa22 (Belén) · Noche · martes 13-10-2026 · Sisiela Barboza. */
    public static function sisiela(): array
    {
        $chica = fn (string $n, float $m, float $b, float $s, float $l, float $t) => ['nombre' => $n, 'manillas' => $m, 'bot' => $b, 'shampagne' => $s, 'limpieza' => $l, 'total' => $t];

        return [
            'planilla' => [
                'encargada' => 'Sisiela Barboza', 'fecha' => '2026-10-13', 'turno' => 'noche',
                'ingresos' => ['caja_chica' => 1000, 'ventas_bebidas' => 7280, 'covers' => 120, 'shampagne' => 1300, 'comision_limpieza' => 60],
                'otros_ingresos' => [['concepto' => '1 papa sin comandar', 'monto' => 20]],
                'total_ingresos' => 8780,
                'egresos' => ['pago_chicas' => 3680, 'pago_garzones' => 364, 'pago_personal' => 410, 'comision_taxis' => 150, 'compra_papel' => 13, 'taxis_pt_frio' => 60],
                'total_egresos' => 4677, 'total' => 4103, 'qr' => 1266, 'efectivo' => 2837, 'efectivo_en_sobre' => 2837,
                'garzones' => [
                    ['nombre' => 'Edwin', 'total_vendido' => 4260, 'pagado' => 213],
                    ['nombre' => 'Cristhian', 'total_vendido' => 3020, 'pagado' => 151],
                ],
                'personal' => [
                    ['nombre' => 'J. Pablo', 'cargo' => 'Seguridad', 'pagado' => 28],
                    ['nombre' => 'Elizabet', 'cargo' => 'Aseo', 'pagado' => 50],
                    ['nombre' => 'Sergio', 'cargo' => 'Barra', 'pagado' => 50],
                    ['nombre' => 'Sisiela', 'cargo' => 'Cajera', 'pagado' => 282],
                ],
            ],
            'pago_chicas' => [
                $chica('Angy', 80, 0, 0, 0, 80),
                $chica('Karime', 40, 50, 0, 0, 90),
                $chica('Susana', 0, 100, 0, 0, 100),
                $chica('Alba', 120, 150, 0, 10, 270),
                $chica('Ruby', 120, 200, 240, 10, 560),
                $chica('Luz', 320, 0, 360, 10, 680),
                $chica('Yenny', 0, 50, 0, 0, 50),
                $chica('J. Pablo', 0, 50, 0, 0, 50),
                $chica('Edwin', 40, 0, 0, 0, 40),
                $chica('Camila', 480, 0, 0, 10, 480),
                $chica('Cloe', 320, 100, 180, 10, 600),
                $chica('Dulce', 560, 0, 0, 10, 560),
                $chica('Luz', 120, 0, 0, 0, 120),
            ],
            'control_shampagne' => [
                ['nombre' => 'Ruby', 'total' => 400, 'pagado' => 240],
                ['nombre' => 'Cloe', 'total' => 300, 'pagado' => 180],
                ['nombre' => 'Luz', 'total' => 600, 'pagado' => 360],
            ],
            'pos' => [
                'total_cobros' => 8760, 'efectivo' => 4970, 'qr' => 3790, 'limpieza' => 60,
                'bebida_chicas' => 5800, 'shampagne' => 1300,
                'chicas' => [
                    'ALBA' => [100 => 3, 80 => 3], 'ANGIE' => [80 => 2], 'CAMILA' => [80 => 12],
                    'CLOE' => [80 => 8, 100 => 2], 'DULCE' => [80 => 14], 'EDWIN' => [80 => 1],
                    'JUAN PABLO' => [100 => 1], 'KARIME' => [80 => 1, 100 => 1], 'LUZ' => [80 => 11],
                    'RUBY' => [80 => 3, 100 => 4], 'SUSANA' => [100 => 2], 'YENNY' => [100 => 1],
                ],
            ],
            'sobre' => ['monto' => 2837],
        ];
    }
}
