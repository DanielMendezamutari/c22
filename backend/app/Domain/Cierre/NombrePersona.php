<?php

namespace App\Domain\Cierre;

/**
 * Compara nombres escritos a mano y en el POS.
 *  - Coincidencia segura (mismaPersona): "Fanny" = "Fanny Morón", "J. Pablo" = "JUAN PABLO" = "Juan Pablo Perez".
 *    Nunca une nombres completos distintos ("Juan Perez" ≠ "Jose Perez", "Maria Perez" ≠ "Mario Lopez").
 *  - Coincidencia aproximada (parecido): errores de escritura o diminutivos de un nombre suelto
 *    ("Angy" ~ "ANGIE", "Abi" ~ "ABY", "Nati" ~ "Natalia");
 *    quien la use debe exigir que sea única y dejarla como alerta para revisión humana.
 */
final class NombrePersona
{
    public static function normalizar(string $nombre): string
    {
        $n = mb_strtolower(trim($nombre));
        $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $n = preg_replace('/[^a-z ]+/', ' ', $n);

        return trim(preg_replace('/\s+/', ' ', $n));
    }

    public static function mismaPersona(string $a, string $b): bool
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if ($ta === [] || $tb === []) {
            return false;
        }

        // El nombre más corto debe ser prefijo del más largo; el primer token puede ser una inicial
        [$corto, $largo] = count($ta) <= count($tb) ? [$ta, $tb] : [$tb, $ta];
        foreach ($corto as $i => $token) {
            $coincide = $token === $largo[$i]
                || ($i === 0 && (strlen($token) === 1 || strlen($largo[0]) === 1) && $token[0] === $largo[0][0] && count($corto) > 1);
            if (!$coincide) {
                return false;
            }
        }

        return true;
    }

    public static function parecido(string $a, string $b): bool
    {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if (count($ta) !== 1 || count($tb) !== 1 || $ta[0][0] !== $tb[0][0]) {
            return false;
        }
        [$x, $y] = [$ta[0], $tb[0]];
        $corto = min(strlen($x), strlen($y));

        // Diminutivo: el nombre corto (≤ 4 letras) comparte las 3 primeras con el largo ("Nati" ~ "Natalia", "Fio" ~ "Fiorella")
        if ($corto >= 3 && $corto <= 4 && strlen($x) !== strlen($y) && strncmp($x, $y, 3) === 0) {
            return true;
        }

        // Error de escritura: 1 letra en nombres de 3 ("Abi" ~ "Aby"), hasta 2 desde 4 letras ("Angy" ~ "Angie")
        return $corto >= 3 && levenshtein($x, $y) <= ($corto >= 4 ? 2 : 1);
    }

    /**
     * Agrupa montos por persona (coincidencia segura); la clave es el primer nombre visto.
     *
     * @param array<int, array{0: string, 1: float}> $pares
     * @return array<string, float>
     */
    public static function agrupar(array $pares): array
    {
        $grupos = [];
        foreach ($pares as [$nombre, $monto]) {
            $clave = self::buscar($nombre, array_keys($grupos)) ?? $nombre;
            $grupos[$clave] = ($grupos[$clave] ?? 0) + $monto;
        }

        return $grupos;
    }

    public static function buscar(string $nombre, array $candidatos): ?string
    {
        foreach ($candidatos as $c) {
            if (self::mismaPersona($nombre, (string) $c)) {
                return (string) $c;
            }
        }

        return null;
    }

    /** Coincidencia aproximada solo si hay exactamente un candidato parecido. */
    public static function buscarAproximado(string $nombre, array $candidatos): ?string
    {
        $parecidos = array_values(array_filter($candidatos, fn ($c) => self::parecido($nombre, (string) $c)));

        return count($parecidos) === 1 ? (string) $parecidos[0] : null;
    }

    private static function tokens(string $nombre): array
    {
        $n = self::normalizar($nombre);

        return $n === '' ? [] : explode(' ', $n);
    }
}
