<?php

namespace App\Console\Commands;

use App\Application\UseCases\Cierre\ConciliarLotesListosUseCase;
use Illuminate\Console\Command;

class ConciliarCierresPendientesCommand extends Command
{
    protected $signature = 'cierres:conciliar-pendientes';

    protected $description = 'Concilia lotes de cierre que cumplieron el tiempo de espera, sin envíos.';

    public function handle(ConciliarLotesListosUseCase $conciliar): int
    {
        $this->info('Lotes conciliados: '.$conciliar->ejecutar());

        return self::SUCCESS;
    }
}
