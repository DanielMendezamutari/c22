<?php

namespace App\Application\UseCases\Cierre;

use App\Domain\Cierre\RedactorAvisoCierre;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use App\Services\WhatsAppOutboundDispatcherService;
use Illuminate\Support\Facades\Cache;

class NotificarLotesConciliadosUseCase
{
    public function __construct(private WhatsAppOutboundDispatcherService $dispatcher, private RedactorAvisoCierre $redactor) {}

    public function ejecutar(): int
    {
        $jid = config('services.conciliacion.aviso_grupo_jid');
        if (! is_string($jid) || ! str_ends_with($jid, '@g.us')) {
            return 0;
        }
        $cantidad = 0;
        $candidatos = fn () => LoteCierre::where('estado', 'conciliado')->whereNotNull('resultado')
            ->whereBetween('conciliado_at', [now()->subHours(36), now()])
            ->when(config('services.conciliacion.aviso_desde'), fn ($q, $desde) => $q->whereDate('fecha_operativa', '>=', $desde));
        foreach ($candidatos()->pluck('id') as $id) {
            $lock = Cache::lock('aviso-lote:'.$id, 120);
            if (! $lock->get()) {
                continue;
            }
            try {
                $lote = $candidatos()->whereKey($id)->first();
                if (! $lote) {
                    continue;
                }
                $resultado = $lote->resultado;
                $reglas = array_values(array_map(fn ($regla) => [
                    'codigo' => $regla['codigo'] ?? null,
                    'estado' => $regla['estado'],
                    'esperado' => $regla['esperado'] ?? null,
                    'declarado' => $regla['declarado'] ?? null,
                ], array_filter($resultado['reglas'] ?? [], fn ($regla) => in_array($regla['estado'] ?? null, ['no_cumple', 'sin_verificar'], true))));
                $huella = hash('sha256', json_encode([$resultado['estado'] ?? null, $reglas, $resultado['documentos_faltantes'] ?? []], JSON_THROW_ON_ERROR));
                if ($lote->aviso_huella === $huella) {
                    continue;
                }
                $planilla = $lote->documentos()->where('tipo', 'planilla_ventas')->orderByDesc('confianza')->orderBy('id')->first()?->datos;
                $texto = $this->redactor->redactar(
                    $lote->sucursal->nombre, $lote->fecha_operativa->toDateString(), $lote->tipo_turno,
                    $lote->encargada, $resultado, $planilla, $lote->aviso_enviado_at !== null,
                )."\nRef. lote #{$lote->id}";
                $envio = $this->dispatcher->enviarMensaje($jid, $texto, null, 'aviso_lote_cierre', $lote->sucursal_id, null);
                if ($envio['success'] ?? false) {
                    $lote->update(['aviso_enviado_at' => now(), 'aviso_huella' => $huella]);
                    $cantidad++;
                }
            } finally {
                $lock->release();
            }
        }

        return $cantidad;
    }
}
