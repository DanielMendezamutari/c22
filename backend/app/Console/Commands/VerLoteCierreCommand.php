<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use Illuminate\Console\Command;

class VerLoteCierreCommand extends Command
{
    protected $signature = 'cierres:ver {lote_id}';

    protected $description = 'Muestra la evidencia y el resultado guardado de un lote de cierre.';

    public function handle(): int
    {
        $lote = LoteCierre::with(['documentos.inbound', 'sucursal'])->find($this->argument('lote_id'));
        if (! $lote) {
            $this->error('Lote no encontrado.');

            return self::FAILURE;
        }
        $this->info("{$lote->sucursal->nombre} | {$lote->fecha_operativa->toDateString()} | {$lote->tipo_turno} | {$lote->estado}");
        $this->table(['Tipo', 'Mensaje', 'Evidencia de casa'], $lote->documentos->map(fn ($d) => [
            $d->tipo, $d->inbound_id, $d->evidencia_casa,
        ])->all());
        $this->line(json_encode($lote->resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
