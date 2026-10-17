<?php

namespace App\Console\Commands;

use App\Domain\Cierre\EvaluadorExtraccion;
use App\Infrastructure\AI\Cierre\ExtractorDocumentosCierre;
use Illuminate\Console\Command;

/**
 * Mide la precisión de la lectura de la IA contra cierres reales leídos por una persona (spec 005 T010).
 * Cada imagen <nombre>.jpg|png|pdf va con <nombre>.esperado.json. Las fotos tienen datos personales: viven fuera de git.
 *
 *   php artisan cierres:evaluar-ia ../specs/005-conciliacion-cierre-ia/fixtures/dorados
 */
class EvaluarExtraccionIaCommand extends Command
{
    protected $signature = 'cierres:evaluar-ia {directorio} {--solo= : Evaluar solo los archivos que contengan este texto}';

    protected $description = 'Mide qué porcentaje de campos lee bien la IA en los cierres de referencia';

    public function handle(ExtractorDocumentosCierre $extractor, EvaluadorExtraccion $evaluador): int
    {
        $dir = realpath($this->argument('directorio')) ?: rtrim($this->argument('directorio'), '/');
        $esperados = glob("{$dir}/*.esperado.json") ?: [];
        if ($esperados === []) {
            $this->error("No hay archivos *.esperado.json en {$dir}");

            return self::FAILURE;
        }

        @mkdir("{$dir}/resultados", 0775, true);
        $filas = [];
        $total = ['tipos_esperados' => 0, 'tipos_encontrados' => 0, 'campos' => 0, 'campos_ok' => 0];
        $porTipo = [];

        foreach ($esperados as $archivoEsperado) {
            $base = substr($archivoEsperado, 0, -strlen('.esperado.json'));
            if ($this->option('solo') && !str_contains(basename($base), $this->option('solo'))) {
                continue;
            }
            $imagen = collect(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->map(fn ($e) => "{$base}.{$e}")->first(fn ($f) => is_file($f));
            if (!$imagen) {
                $this->warn('Sin imagen para ' . basename($base));

                continue;
            }

            $mime = match (strtolower(pathinfo($imagen, PATHINFO_EXTENSION))) {
                'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
            };
            $leido = $extractor->extraer($imagen, $mime);
            file_put_contents("{$dir}/resultados/" . basename($base) . '.json', json_encode($leido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $esperado = json_decode(file_get_contents($archivoEsperado), true)['documentos'];
            $r = $evaluador->evaluar($esperado, $leido['ia_disponible'] ? $leido['documentos'] : []);
            if (!$leido['ia_disponible']) {
                array_unshift($r['errores'], 'IA no disponible: ' . $leido['error']);
            }

            foreach (['tipos_esperados', 'tipos_encontrados', 'campos', 'campos_ok'] as $k) {
                $total[$k] += $r[$k];
            }
            foreach ($esperado as $doc) {
                $porTipo[$doc['tipo']] ??= ['archivos' => 0, 'campos' => 0, 'campos_ok' => 0];
                $porTipo[$doc['tipo']]['archivos']++;
            }
            $tipoPrincipal = $esperado[0]['tipo'];
            $porTipo[$tipoPrincipal]['campos'] += $r['campos'];
            $porTipo[$tipoPrincipal]['campos_ok'] += $r['campos_ok'];

            $filas[] = [basename($base), "{$r['tipos_encontrados']}/{$r['tipos_esperados']}", "{$r['campos_ok']}/{$r['campos']}", $this->pct($r['campos_ok'], $r['campos'])];
            foreach ($r['errores'] as $error) {
                $this->line("  <fg=red>✗</> " . basename($base) . ": {$error}");
            }
        }

        $this->newLine();
        $this->table(['Archivo', 'Documentos', 'Campos', '%'], $filas);
        $this->table(['Tipo', 'Archivos', 'Campos', '%'], collect($porTipo)->map(fn ($v, $k) => [$k, $v['archivos'], "{$v['campos_ok']}/{$v['campos']}", $this->pct($v['campos_ok'], $v['campos'])])->values()->all());
        $this->info(sprintf(
            'TOTAL · documentos detectados %d/%d (%s) · campos correctos %d/%d (%s)',
            $total['tipos_encontrados'], $total['tipos_esperados'], $this->pct($total['tipos_encontrados'], $total['tipos_esperados']),
            $total['campos_ok'], $total['campos'], $this->pct($total['campos_ok'], $total['campos']),
        ));

        return self::SUCCESS;
    }

    private function pct(int $ok, int $total): string
    {
        return $total === 0 ? '—' : number_format(100 * $ok / $total, 1) . '%';
    }
}
