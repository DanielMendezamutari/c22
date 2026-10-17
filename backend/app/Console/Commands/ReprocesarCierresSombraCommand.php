<?php

namespace App\Console\Commands;

use App\Application\UseCases\Cierre\ConciliarLotesListosUseCase;
use App\Application\UseCases\Cierre\RegistrarDocumentosUseCase;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReprocesarCierresSombraCommand extends Command
{
    protected $signature = 'cierres:reprocesar-sombra {--desde=} {--hasta=} {--limite=200}';

    protected $description = 'Registra y concilia evidencia histórica sin modificar el flujo contable ni enviar mensajes.';

    public function handle(RegistrarDocumentosUseCase $registrar, ConciliarLotesListosUseCase $conciliar): int
    {
        $consulta = WhatsAppMensajeInbound::query();
        $fechas = [];
        foreach (['desde' => '>=', 'hasta' => '<='] as $opcion => $comparador) {
            if ($this->option($opcion) === null) {
                continue;
            }
            $valor = $this->option($opcion);
            if (! Carbon::canBeCreatedFromFormat($valor, 'Y-m-d H:i')) {
                $this->error("--{$opcion} debe usar Y-m-d H:i (hora de Bolivia).");

                return self::INVALID;
            }
            $fechas[$opcion] = Carbon::createFromFormat('!Y-m-d H:i', $valor, 'America/La_Paz')->utc();
            $consulta->where('created_at', $comparador, $fechas[$opcion]);
        }
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limite === false || (isset($fechas['desde'], $fechas['hasta']) && $fechas['desde']->gt($fechas['hasta']))) {
            $this->error('Rango inválido o límite no positivo.');

            return self::INVALID;
        }
        $ids = $consulta->orderBy('created_at')->orderBy('id')->limit($limite)->pluck('id');
        foreach ($ids as $id) {
            $registrar->ejecutar($id);
        }
        $espera = config('services.conciliacion.minutos_espera');
        try {
            config(['services.conciliacion.minutos_espera' => 0]);
            $cantidad = $conciliar->ejecutar();
        } finally {
            config(['services.conciliacion.minutos_espera' => $espera]);
        }
        $this->info("Mensajes procesados: {$ids->count()}. Lotes conciliados: {$cantidad}.");
        $lotes = LoteCierre::with('sucursal')->whereHas('documentos', fn ($q) => $q->whereIn('inbound_id', $ids))->orderBy('id')->get();
        $this->table(['Casa', 'Fecha', 'Turno', 'Encargada', 'Conciliación', 'Reglas que no cumplen', 'Documentos faltantes'], $lotes->map(function ($lote) {
            $r = $lote->resultado ?? [];

            return [
                $lote->sucursal->nombre, $lote->fecha_operativa->toDateString(), $lote->tipo_turno, $lote->encargada,
                $lote->estado === 'conciliado' ? ($r['estado'] ?? '') : $lote->estado,
                collect($r['reglas'] ?? [])->where('estado', 'no_cumple')->pluck('codigo')->implode(', '),
                implode(', ', $r['documentos_faltantes'] ?? []),
            ];
        })->all());

        return self::SUCCESS;
    }
}
