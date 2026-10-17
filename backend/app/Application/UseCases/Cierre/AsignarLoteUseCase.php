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
        $this->asignarConReferencia($doc, $doc->inbound->created_at);
    }

    private function asignarConReferencia(DocumentoCierre $doc, Carbon $referencia): void
    {
        if ($doc->lote_id) {
            return;
        }
        $inbound = $doc->inbound;
        $datos = $doc->datos;
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
            $ancla = $delRemitente()->whereIn('evidencia_casa', ['encabezado', 'grupo'])->latest('id')->first();
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
        [$fecha, $turno, $propio, $fechaPropia] = $this->fechaTurno($doc);
        if ($casa && ! $propio && trim((string) $inbound->sender_phone) !== '') {
            $anterior = $delRemitente()->where('sucursal_id', $casa)->latest('id')->first();
            if ($anterior) {
                if (! $fechaPropia) {
                    $fecha = $anterior->fecha_operativa->toDateString();
                }
                $turno = $anterior->tipo_turno;
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
                if ($lote->estado === 'conciliado') {
                    $lote->estado = 'abierto';
                    $lote->conciliado_at = null;
                }
                $lote->save();
                $doc->lote_id = $lote->id;
            }
            $doc->save();
        });
        if (in_array($evidencia, ['encabezado', 'grupo'], true) && trim((string) $inbound->sender_phone) !== '') {
            DocumentoCierre::whereNull('sucursal_id')->where('id', '!=', $doc->id)
                ->whereHas('inbound', fn ($q) => $q->where('sender_phone', $inbound->sender_phone)->whereBetween('created_at', [$desde, $inbound->created_at]))
                ->get()->each(fn ($pendiente) => $this->asignarConReferencia($pendiente, $referencia));
        }
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
                return [$inicio->toDateString(), $inicio->hour >= 17 || $inicio->hour < 5 ? 'noche' : 'dia', true, true];
            }
            // Fecha ilegible: usar hora del mensaje.
        }
        $propio = false;
        $fecha = null;
        if (in_array($doc->tipo, ['planilla_ventas', 'control_shampagne', 'sobre'], true)) {
            $fecha = $datos['fecha'] ?? null;
            $leido = Str::lower(Str::ascii($datos['turno'] ?? ''));
            $propio = in_array($leido, ['dia', 'tarde', 'noche'], true);
            if ($propio) {
                $turno = $leido === 'noche' ? 'noche' : 'dia';
            }
        }
        [$fecha] = ProcesarMensajeWhatsAppJob::fechaOperativa($fecha, $turno, $doc->inbound->created_at);

        return [$fecha, $turno, $propio, ! empty($datos['fecha']) && in_array($doc->tipo, ['planilla_ventas', 'control_shampagne', 'sobre'], true)];
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
