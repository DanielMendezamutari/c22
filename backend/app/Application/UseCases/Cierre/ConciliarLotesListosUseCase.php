<?php

namespace App\Application\UseCases\Cierre;

use App\Domain\Cierre\ArmadorLote;
use App\Domain\Cierre\ConciliadorCierre;
use App\Domain\Cierre\ReglasPago;
use App\Domain\Cierre\ResultadoConciliacion;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConciliarLotesListosUseCase
{
    public function ejecutar(): int
    {
        $cantidad = 0;
        $limite = now()->subMinutes(max(0, (int) config('services.conciliacion.minutos_espera', 10)));
        $reglas = new ReglasPago(cuentasQrOficiales: config('services.conciliacion.cuentas_qr_oficiales', []));
        $listos = fn () => LoteCierre::where('estado', '!=', 'conciliado')->where('ultimo_documento_at', '<=', $limite)
            ->whereHas('documentos', fn ($q) => $q->where('tipo', 'planilla_ventas'));
        foreach ($listos()->pluck('id') as $id) {
            $lock = Cache::lock('cierre-lote:'.$id, 60);
            if (! $lock->get()) {
                continue;
            }
            try {
                $cantidad += DB::transaction(function () use ($listos, $id, $reglas) {
                    $lote = $listos()->whereKey($id)->lockForUpdate()->first();
                    if (! $lote) {
                        return 0;
                    }
                    $docs = $lote->documentos()->orderBy('id')->get();
                    $armado = (new ArmadorLote($reglas))->armar($docs->map(fn ($d) => [
                        'tipo' => $d->tipo, 'datos' => $d->datos, 'confianza' => $d->confianza, 'creado' => $d->created_at->toIso8601String(),
                    ])->all(), $docs->where('tipo', 'texto')->map(fn ($d) => $d->datos['texto'] ?? '')->implode("\n"));
                    $resultado = (new ConciliadorCierre($reglas))->conciliar($armado)->toArray();
                    $resultado['alertas_armado'] = $armado['alertas_armado'] ?? [];
                    foreach ($resultado['alertas_armado'] as $alerta) {
                        if (str_starts_with($alerta, 'Dos planillas distintas')) {
                            $resultado['estado'] = ResultadoConciliacion::REVISAR;
                        }
                    }
                    $lote->update(['resultado' => $resultado, 'estado' => 'conciliado', 'conciliado_at' => now()]);

                    return 1;
                });
            } finally {
                $lock->release();
            }
        }

        return $cantidad;
    }
}
