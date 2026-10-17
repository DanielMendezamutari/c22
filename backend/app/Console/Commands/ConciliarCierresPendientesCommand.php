<?php

namespace App\Console\Commands;

use App\Application\UseCases\Cierre\ConciliarLotesListosUseCase;
use App\Application\UseCases\Cierre\NotificarLotesConciliadosUseCase;
use Illuminate\Console\Command;

class ConciliarCierresPendientesCommand extends Command
{
    protected $signature = 'cierres:conciliar-pendientes {--sin-avisos : Omite los avisos preliminares a contabilidad}';

    protected $description = 'Concilia lotes de cierre y envía borradores al grupo de contabilidad configurado.';

    public function handle(ConciliarLotesListosUseCase $conciliar, NotificarLotesConciliadosUseCase $notificar): int
    {
        $this->info('Lotes conciliados: '.$conciliar->ejecutar());
        $jid = config('services.conciliacion.aviso_grupo_jid');
        if (! $this->option('sin-avisos') && is_string($jid) && str_ends_with($jid, '@g.us')) {
            $this->info('Avisos enviados: '.$notificar->ejecutar());
        }

        return self::SUCCESS;
    }
}
