<?php

namespace App\Domain\Cierre;

final class CuentaBancaria
{
    public static function coincide(?string $cuenta, array $oficiales): bool
    {
        $digitos = preg_replace('/\D/', '', $cuenta ?? '');
        if ($digitos === '') {
            return false;
        }

        $enmascarada = strpbrk($cuenta, '*xX') !== false;
        $mascara = preg_replace('/[\s-]+/', '', $cuenta);
        foreach ($oficiales as $oficial) {
            $oficial = preg_replace('/\D/', '', (string) $oficial);
            if (!$enmascarada && $digitos === $oficial) {
                return true;
            }
            if ($enmascarada && preg_match('/^(\d{3})[*xX]+(\d{3})$/', $mascara, $partes)
                && strlen($oficial) > 6
                && str_starts_with($oficial, $partes[1]) && str_ends_with($oficial, $partes[2])) {
                return true;
            }
        }

        return false;
    }
}
