<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppGrupoDescubierto;
use App\Services\WhatsAppOutboundDispatcherService;
use Illuminate\Console\Command;

class AvisoPruebaCierreCommand extends Command
{
    protected $signature = 'cierres:aviso-prueba {--grupo= : JID del grupo de contabilidad}';

    protected $description = 'Lista grupos descubiertos o envía un mensaje de prueba al grupo indicado.';

    public function handle(WhatsAppOutboundDispatcherService $dispatcher): int
    {
        $jid = $this->option('grupo');
        if ($jid === null) {
            $this->table(['Nombre', 'remote_jid'], WhatsAppGrupoDescubierto::orderBy('nombre_grupo')->get(['nombre_grupo', 'remote_jid'])->toArray());

            return self::SUCCESS;
        }
        if (! str_ends_with($jid, '@g.us')) {
            $this->error('El JID debe corresponder a un grupo (@g.us).');

            return self::FAILURE;
        }
        $envio = $dispatcher->enviarMensaje($jid, '🧪 Prueba del bot C22: este grupo recibirá los borradores de cierre.', null, 'aviso_lote_cierre', null, null);
        if ($envio['success'] ?? false) {
            $this->info('Prueba enviada.');

            return self::SUCCESS;
        }
        $this->error('No se pudo enviar la prueba.');

        return self::FAILURE;
    }
}
