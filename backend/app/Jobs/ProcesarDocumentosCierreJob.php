<?php

namespace App\Jobs;

use App\Application\UseCases\Cierre\RegistrarDocumentosUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarDocumentosCierreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $inboundId) {}

    public function handle(RegistrarDocumentosUseCase $registrar): void
    {
        $registrar->ejecutar($this->inboundId);
    }
}
