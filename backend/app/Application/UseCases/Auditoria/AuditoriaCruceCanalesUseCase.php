<?php

namespace App\Application\UseCases\Auditoria;

use App\Infrastructure\Persistence\Eloquent\Models\AuditoriaCruceCanales;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuditoriaCruceCanalesUseCase
{
    /**
     * Procesa y cruza un mensaje inbound con canales locales y globales.
     *
     * @param int $mensajeInboundId
     * @param int $sucursalId
     * @param string $tipoEvento ('planilla_cierre', 'gasto_caja_chica', 'voucher_banco')
     * @param float $montoDeclarado
     * @param string|null $hashImagen
     * @param int|null $turnoId
     * @return AuditoriaCruceCanales
     */
    public function ejecutar(
        int $mensajeInboundId,
        int $sucursalId,
        string $tipoEvento,
        float $montoDeclarado,
        ?string $hashImagen = null,
        ?int $turnoId = null
    ): AuditoriaCruceCanales {
        return DB::transaction(function () use (
            $mensajeInboundId,
            $sucursalId,
            $tipoEvento,
            $montoDeclarado,
            $hashImagen,
            $turnoId
        ) {
            $mensaje = WhatsAppMensajeInbound::findOrFail($mensajeInboundId);
            $grupo = $mensaje->grupo;
            $esCanalGlobal = $grupo ? (bool) $grupo->es_global : false;
            $fechaMensaje = $mensaje->created_at ? $mensaje->created_at->toDateString() : Carbon::today()->toDateString();

            // Buscar si ya existe un cruce para la misma imagen (SHA-256) o para la misma sucursal, fecha y tipo de evento
            $queryCruce = AuditoriaCruceCanales::where('sucursal_id', $sucursalId)
                ->where('tipo_evento', $tipoEvento)
                ->whereDate('created_at', $fechaMensaje);

            if ($hashImagen) {
                $queryCruce->where(function ($q) use ($hashImagen) {
                    $q->where('hash_imagen', $hashImagen)
                      ->orWhereNull('hash_imagen');
                });
            }

            $cruce = $queryCruce->latest('id')->first();

            if (!$cruce) {
                $cruce = new AuditoriaCruceCanales([
                    'sucursal_id' => $sucursalId,
                    'turno_id' => $turnoId,
                    'tipo_evento' => $tipoEvento,
                    'hash_imagen' => $hashImagen,
                ]);
            }

            if ($esCanalGlobal) {
                $cruce->mensaje_global_inbound_id = $mensajeInboundId;
                $cruce->monto_declarado_global = $montoDeclarado;

                if ($cruce->mensaje_local_inbound_id && $cruce->monto_declarado_local !== null) {
                    // Ambos canales presentes: calcular discrepancia
                    $montoLocal = (float) $cruce->monto_declarado_local;
                    $montoGlobal = (float) $cruce->monto_declarado_global;
                    $diferencia = round($montoLocal - $montoGlobal, 2);
                    $cruce->diferencia_bs = $diferencia;

                    if (abs($diferencia) < 0.01) {
                        $cruce->estado_cruce = 'conforme_doble_canal';
                        $cruce->observaciones = 'Comprobante verificado concurrentemente en canal local y global sin diferencias.';
                    } else {
                        $cruce->estado_cruce = 'discrepancia_detectada';
                        $cruce->observaciones = sprintf(
                            'Discrepancia detectada: Local reportó %.2f Bs vs Global reportó %.2f Bs (Diferencia: %.2f Bs).',
                            $montoLocal,
                            $montoGlobal,
                            $diferencia
                        );
                    }
                } else {
                    $cruce->estado_cruce = 'solo_canal_global';
                    $cruce->diferencia_bs = 0.00;
                    $cruce->observaciones = 'Publicado únicamente en el canal global; a la espera del reporte local.';
                }
            } else {
                $cruce->mensaje_local_inbound_id = $mensajeInboundId;
                $cruce->monto_declarado_local = $montoDeclarado;

                if ($cruce->mensaje_global_inbound_id && $cruce->monto_declarado_global !== null) {
                    // Ambos canales presentes: calcular discrepancia
                    $montoLocal = (float) $cruce->monto_declarado_local;
                    $montoGlobal = (float) $cruce->monto_declarado_global;
                    $diferencia = round($montoLocal - $montoGlobal, 2);
                    $cruce->diferencia_bs = $diferencia;

                    if (abs($diferencia) < 0.01) {
                        $cruce->estado_cruce = 'conforme_doble_canal';
                        $cruce->observaciones = 'Comprobante verificado concurrentemente en canal local y global sin diferencias.';
                    } else {
                        $cruce->estado_cruce = 'discrepancia_detectada';
                        $cruce->observaciones = sprintf(
                            'Discrepancia detectada: Local reportó %.2f Bs vs Global reportó %.2f Bs (Diferencia: %.2f Bs).',
                            $montoLocal,
                            $montoGlobal,
                            $diferencia
                        );
                    }
                } else {
                    $cruce->estado_cruce = 'solo_canal_local';
                    $cruce->diferencia_bs = 0.00;
                    $cruce->observaciones = 'Reportado en canal local de la sucursal.';
                }
            }

            if ($hashImagen && !$cruce->hash_imagen) {
                $cruce->hash_imagen = $hashImagen;
            }

            if ($turnoId && !$cruce->turno_id) {
                $cruce->turno_id = $turnoId;
            }

            $cruce->save();

            return $cruce;
        });
    }
}
