<?php

namespace App\Domain\Cierre;

final class Dinero
{
    public static function centavos(?float $monto): ?int
    {
        return $monto === null ? null : (int) round($monto * 100);
    }

    /**
     * Suma estricta: si algún monto es null (dato no leído) el resultado es null, para que la regla
     * quede "sin verificar" en vez de tratar lo desconocido como cero.
     */
    public static function suma(array $montos): ?float
    {
        if ($montos === [] ) {
            return 0.0;
        }
        foreach ($montos as $m) {
            if ($m === null) {
                return null;
            }
        }

        return array_sum(array_map(fn ($m) => Dinero::centavos((float) $m), $montos)) / 100;
    }

    public static function porcentaje(float $monto, float $pct): float
    {
        return round($monto * $pct / 100, 2);
    }

    public static function formato(?float $monto): string
    {
        return $monto === null ? '—' : number_format($monto, 2, ',', '.');
    }
}
