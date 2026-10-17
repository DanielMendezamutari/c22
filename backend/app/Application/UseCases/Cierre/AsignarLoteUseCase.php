<?php

namespace App\Application\UseCases\Cierre;

use App\Infrastructure\Persistence\Eloquent\Models\DocumentoCierre;
use App\Infrastructure\Persistence\Eloquent\Models\LoteCierre;
use App\Infrastructure\Persistence\Eloquent\Models\SucursalWhatsAppGrupo;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use App\Jobs\ProcesarMensajeWhatsAppJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AsignarLoteUseCase
{
    public function asignar(DocumentoCierre $doc): void
    {
        DB::transaction(fn () => $this->asignarConReferencia($doc, $doc->inbound->created_at));
    }

    private function asignarConReferencia(DocumentoCierre $doc, Carbon $referencia): void
    {
        if ($doc->lote_id) {
            $this->aplicarTurnoRemitente($doc);
            $this->aplicarEncabezadoRemitente($doc);

            return;
        }
        $inbound = $doc->inbound;
        $datos = $doc->datos;
        [$fecha, $turno] = $this->fechaTurno($doc);
        $evidencia = null;
        $casa = $doc->tipo === 'reporte_pos'
            ? ProcesarMensajeWhatsAppJob::inferirSucursalId(['datos' => ['servidor_cajero' => $datos['servidor'] ?? null]], $inbound, true) : null;
        if ($casa) {
            $evidencia = 'encabezado';
        } else {
            $casa = SucursalWhatsAppGrupo::where('remote_jid', $inbound->remote_jid)
                ->where('activo', true)->where('es_global', false)->value('sucursal_id');
            if ($casa) {
                $evidencia = 'grupo';
            }
        }
        if (! $casa && $doc->tipo === 'planilla_ventas') {
            $sinTexto = clone $inbound;
            $sinTexto->raw_text = '';
            $casa = ProcesarMensajeWhatsAppJob::inferirSucursalId(['datos' => ['cajero_nombre' => $datos['encargada'] ?? null]], $sinTexto);
            if ($casa) {
                $evidencia = 'encargada';
            }
        }
        // Ventana relativa al envío, también durante el reprocesamiento histórico.
        $desde = $referencia->copy()->subHours(16);
        $hasta = $referencia;
        $delRemitente = fn () => DocumentoCierre::whereNotNull('lote_id')
            ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $inbound->sender_phone)->whereBetween('created_at', [$desde, $hasta]))
            ->where('id', '!=', $doc->id)
            ->orderByDesc(WhatsAppMensajeInbound::select('created_at')->whereColumn('whatsapp_mensajes_inbound.id', 'documentos_cierre.inbound_id'));
        if (! $casa && trim((string) $inbound->sender_phone) !== '') {
            $ancla = $delRemitente()->where('evidencia_casa', 'grupo')->latest('id')->first();
            if ($ancla) {
                $casa = $ancla->sucursal_id;
                $evidencia = 'remitente';
            }
        }
        if (! $casa) {
            $casa = ProcesarMensajeWhatsAppJob::inferirSucursalId(['datos' => []], $inbound);
            if ($casa) {
                $evidencia = 'texto';
            }
        }
        DB::transaction(function () use ($doc, $casa, $evidencia, $fecha, $turno, $inbound) {
            if (DocumentoCierre::whereKey($doc->id)->lockForUpdate()->value('lote_id')) {
                return;
            }
            $doc->fill(['sucursal_id' => $casa, 'evidencia_casa' => $evidencia, 'fecha_operativa' => $fecha, 'tipo_turno' => $turno]);
            if ($casa) {
                $lote = LoteCierre::firstOrCreate(['sucursal_id' => $casa, 'fecha_operativa' => $fecha, 'tipo_turno' => $turno], ['estado' => 'abierto']);
                $lote = LoteCierre::whereKey($lote->id)->lockForUpdate()->firstOrFail();
                $lote->remitentes = array_values(array_unique(array_filter([...($lote->remitentes ?? []), $inbound->sender_phone])));
                $lote->ultimo_documento_at = now();
                if ($doc->tipo === 'planilla_ventas') {
                    $lote->encargada = $doc->datos['encargada'] ?? null;
                }
                $this->reabrir($lote);
                $lote->save();
                $doc->lote_id = $lote->id;
            }
            $doc->save();
        });
        $this->aplicarTurnoRemitente($doc);
        $this->aplicarEncabezadoRemitente($doc);
        if ($evidencia === 'grupo' && trim((string) $inbound->sender_phone) !== '') {
            DocumentoCierre::whereNull('sucursal_id')->where('id', '!=', $doc->id)
                ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $inbound->sender_phone)->whereBetween('created_at', [$desde, $hasta]))
                ->get()->each(fn ($pendiente) => $this->asignarConReferencia($pendiente, $referencia));
        }
    }

    /** El encabezado vincula documentos del mismo cierre, independientemente del grupo de envío. */
    private function aplicarEncabezadoRemitente(DocumentoCierre $doc): void
    {
        $inbound = $doc->inbound;
        if (trim((string) $inbound->sender_phone) === '') {
            return;
        }
        DB::transaction(function () use ($doc, $inbound) {
            $documentos = DocumentoCierre::whereDate('fecha_operativa', $doc->fecha_operativa)
                ->where('tipo_turno', $doc->tipo_turno)
                ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $inbound->sender_phone)
                    ->whereBetween('created_at', [$inbound->created_at->copy()->subHours(8), $inbound->created_at->copy()->addHours(8)]))
                ->orderBy('id')->lockForUpdate()->get();
            $casas = $documentos->where('evidencia_casa', 'encabezado')->pluck('sucursal_id')->filter()->unique();
            if ($casas->count() > 1) {
                foreach (LoteCierre::whereIn('id', $documentos->pluck('lote_id')->filter())->lockForUpdate()->get() as $lote) {
                    $resultado = $lote->resultado ?? [];
                    $alerta = 'El remitente envió documentos de dos casas distintas';
                    $resultado['alertas_asignacion'] = array_values(array_unique([...($resultado['alertas_asignacion'] ?? []), $alerta]));
                    $resultado['alertas'] = array_values(array_unique([...($resultado['alertas'] ?? []), $alerta]));
                    $lote->update(['resultado' => $resultado]);
                }

                return;
            }
            if ($casas->count() !== 1) {
                return;
            }
            $casa = $casas->first();
            $afectados = [];
            foreach ($documentos as $documento) {
                if ($documento->sucursal_id !== null && ! in_array($documento->evidencia_casa, ['grupo', 'remitente', 'texto'], true)) {
                    continue;
                }
                $destino = LoteCierre::firstOrCreate([
                    'sucursal_id' => $casa, 'fecha_operativa' => $documento->fecha_operativa->toDateString(),
                    'tipo_turno' => $documento->tipo_turno,
                ], ['estado' => 'abierto']);
                if ($documento->lote_id !== $destino->id) {
                    $afectados[] = $documento->lote_id;
                    $afectados[] = $destino->id;
                }
                $documento->update(['sucursal_id' => $casa, 'lote_id' => $destino->id, 'evidencia_casa' => 'encabezado_remitente']);
            }
            $this->actualizarLotesAfectados($afectados);
        });
    }

    /** Heredar solo de documentos con turno propio, en ambas direcciones de llegada. */
    private function aplicarTurnoRemitente(DocumentoCierre $doc): void
    {
        $telefono = $doc->inbound->sender_phone;
        if (! $doc->sucursal_id || trim((string) $telefono) === '') {
            return;
        }
        $documentos = DocumentoCierre::with('inbound')->where('sucursal_id', $doc->sucursal_id)
            ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $telefono)
                ->whereBetween('created_at', [$doc->inbound->created_at->copy()->subHours(8), $doc->inbound->created_at->copy()->addHours(8)]))
            ->orderBy('id')->lockForUpdate()->get();
        $afectados = [];
        foreach ($documentos as $documento) {
            if ($this->fechaTurno($documento)[2]) {
                continue;
            }
            // Cada evidencia usa su propia ventana: no propagar por cadenas de documentos.
            $anclas = DocumentoCierre::with('inbound')->where('sucursal_id', $documento->sucursal_id)
                ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $telefono)
                    ->whereBetween('created_at', [$documento->inbound->created_at->copy()->subHours(8), $documento->inbound->created_at->copy()->addHours(8)]))
                ->whereIn('tipo', ['planilla_ventas', 'reporte_pos', 'control_shampagne'])
                ->get()->filter(fn ($ancla) => $this->fechaTurno($ancla)[2]);
            $distancia = fn ($ancla) => abs($ancla->inbound->created_at->getTimestamp() - $documento->inbound->created_at->getTimestamp());
            $prioridad = ['planilla_ventas' => 0, 'reporte_pos' => 1, 'control_shampagne' => 2];
            // Si rindió ambos turnos, primero elegir el turno más cercano al envío.
            if ($anclas->pluck('tipo_turno')->unique()->count() > 1) {
                $cercana = $anclas->sortBy(fn ($ancla) => [$distancia($ancla), $prioridad[$ancla->tipo], $ancla->id])->first();
                $anclas = $anclas->where('tipo_turno', $cercana->tipo_turno);
            }
            $ancla = $anclas->sortBy(fn ($ancla) => [$prioridad[$ancla->tipo], $distancia($ancla), $ancla->id])->first();
            if (! $ancla) {
                continue;
            }
            [$fecha, $turno] = $this->fechaTurno($ancla);
            $destino = LoteCierre::firstOrCreate([
                'sucursal_id' => $documento->sucursal_id, 'fecha_operativa' => $fecha, 'tipo_turno' => $turno,
            ], ['estado' => 'abierto']);
            if ($documento->lote_id !== $destino->id) {
                $afectados[] = $documento->lote_id;
                $afectados[] = $destino->id;
                $documento->update(['fecha_operativa' => $fecha, 'tipo_turno' => $turno, 'lote_id' => $destino->id]);
            }
        }
        $this->actualizarLotesAfectados($afectados);
        $doc->refresh();
    }

    private function actualizarLotesAfectados(array $afectados): void
    {
        foreach (LoteCierre::whereIn('id', array_filter(array_unique($afectados)))->orderBy('id')->lockForUpdate()->get() as $lote) {
            $restantes = $lote->documentos()->with('inbound')->orderBy('id')->get();
            if ($restantes->isEmpty()) {
                $lote->delete();

                continue;
            }
            // Reconstruir metadatos: una encargada o teléfono puede haber salido del lote.
            $lote->remitentes = $restantes->pluck('inbound.sender_phone')->filter()->unique()->values()->all();
            $lote->encargada = $restantes->where('tipo', 'planilla_ventas')->last()?->datos['encargada'] ?? null;
            $lote->ultimo_documento_at = now();
            $this->reabrir($lote);
            $lote->save();
        }
    }

    private function reabrir(LoteCierre $lote): void
    {
        $alertas = $lote->resultado['alertas_asignacion'] ?? [];
        $lote->estado = 'abierto';
        $lote->conciliado_at = null;
        $lote->resultado = $alertas ? ['alertas_asignacion' => $alertas, 'alertas' => $alertas] : null;
    }

    private function fechaTurno(DocumentoCierre $doc): array
    {
        $datos = $doc->datos;
        $local = $doc->inbound->created_at->copy()->setTimezone('America/La_Paz');
        $turno = $local->hour >= 17 || $local->hour < 14 ? 'noche' : 'dia';
        if ($doc->tipo === 'reporte_pos' && ! empty($datos['desde'])) {
            $inicio = self::fechaBoliviana((string) $datos['desde']);
            // Igual que la planilla: una fecha a más de 2 días del envío es un error de lectura (17-10: "2024")
            if ($inicio && abs($inicio->copy()->startOfDay()->diffInDays($local->copy()->startOfDay())) > 2) {
                $inicio = null;
            }
            if ($inicio) {
                return [$inicio->toDateString(), $inicio->hour >= 17 || $inicio->hour < 5 ? 'noche' : 'dia', true];
            }
            // Fecha ilegible: usar hora del mensaje.
        }
        $propio = false;
        $fecha = null;
        if (in_array($doc->tipo, ['planilla_ventas', 'control_shampagne', 'sobre'], true)) {
            $fecha = $datos['fecha'] ?? null;
            $leido = Str::lower(Str::ascii($datos['turno'] ?? ''));
            $propio = $doc->tipo !== 'sobre' && in_array($leido, ['dia', 'tarde', 'noche'], true);
            if ($propio) {
                $turno = $leido === 'noche' ? 'noche' : 'dia';
            }
        }
        [$fecha] = ProcesarMensajeWhatsAppJob::fechaOperativa($fecha, $turno, $doc->inbound->created_at);

        return [$fecha, $turno, $propio || $doc->tipo === 'planilla_ventas'];
    }

    /**
     * El POS imprime día/mes/año ("16/10/2026 21:05"). Carbon::parse lo leería como mes/día y "03/10/2026"
     * pasaría a ser 10 de marzo sin ningún error; por eso solo se aceptan formatos explícitos.
     */
    public static function fechaBoliviana(string $texto): ?Carbon
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        foreach (['d/m/Y G:i:s', 'd/m/Y G:i', 'd/m/Y', 'd-m-Y G:i', 'd-m-Y', 'Y-m-d G:i:s', 'Y-m-d G:i', 'Y-m-d'] as $formato) {
            $fecha = \DateTime::createFromFormat('!' . $formato, $texto, new \DateTimeZone('America/La_Paz'));
            $errores = \DateTime::getLastErrors();
            // Sin desbordes: "31/02/2026" o "16/13/2026" no se aceptan como otra fecha
            if ($fecha !== false && (!$errores || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
                return Carbon::instance($fecha);
            }
        }

        return null;
    }
}
