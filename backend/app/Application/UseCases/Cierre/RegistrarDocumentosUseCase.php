<?php

namespace App\Application\UseCases\Cierre;

use App\Infrastructure\AI\Cierre\ExtractorDocumentosCierre;
use App\Infrastructure\Persistence\Eloquent\Models\DocumentoCierre;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\WhatsAppMensajeInbound;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegistrarDocumentosUseCase
{
    public function __construct(private ExtractorDocumentosCierre $extractor, private AsignarLoteUseCase $asignador) {}

    public function ejecutar(int $inboundId): void
    {
        Cache::lock('cierre-inbound:'.$inboundId, 300)->block(5, function () use ($inboundId) {
            $inbound = WhatsAppMensajeInbound::find($inboundId);
            if (! $inbound || $inbound->estado === 'duplicado') {
                return;
            }
            // Bot con Baileys 7 (hasta el 17-10): en mensajes descifrados por reintento el remitente quedaba como el
            // número del GRUPO. Ese dato no identifica a nadie y mezclaría los cierres de todas las encargadas.
            $numeroGrupo = strstr((string) $inbound->remote_jid, '@', true);
            if ($numeroGrupo && $inbound->sender_phone === $numeroGrupo) {
                $inbound->sender_phone = '';
                $inbound->save();
            }
            $this->calcularHuella($inbound);
            $existentes = DocumentoCierre::where('inbound_id', $inboundId)->get();
            if ($existentes->isNotEmpty()) {
                foreach ($existentes as $doc) {
                    $this->asignador->asignar($doc);
                }

                return;
            }
            if ($inbound->hash_media) {
                // También completar huellas históricas: esos mensajes llegaron antes del deduplicador.
                $anteriores = WhatsAppMensajeInbound::where('id', '<', $inboundId)
                    ->whereBetween('created_at', [$inbound->created_at->copy()->subHours(48), $inbound->created_at])
                    ->whereIn('id', DocumentoCierre::select('inbound_id'))
                    ->where(fn ($q) => $q->where('hash_media', $inbound->hash_media)->orWhereNull('hash_media'))
                    ->orderBy('id')->get();
                foreach ($anteriores as $anterior) {
                    $this->calcularHuella($anterior);
                    if ($anterior->hash_media === $inbound->hash_media) {
                        Log::warning('Documento duplicado omitido en cierre sombra.', [
                            'inbound_id' => $inboundId, 'original_inbound_id' => $anterior->id,
                            'hash_media' => $inbound->hash_media,
                        ]);

                        return;
                    }
                }
            }
            $documentos = [];
            if ($inbound->media_path) {
                $mime = match (strtolower(pathinfo($inbound->media_path, PATHINFO_EXTENSION))) {
                    'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
                };
                $casas = Sucursal::where('activo', true)->with(['usuarios' => fn ($q) => $q->where('activo', true)->whereIn('rol', ['cajera', 'barman'])])->get();
                $respuesta = $this->extractor->extraer(
                    $inbound->media_path, $mime,
                    "Remitente: {$inbound->sender_name} | Tel: {$inbound->sender_phone} | Texto adjunto: {$inbound->raw_text}",
                    $casas->map(fn ($c) => "{$c->nombre}: {$c->alias_documentos}")->implode("\n"),
                    $casas->map(fn ($c) => $c->nombre.': '.$c->usuarios->map(fn ($u) => trim("{$u->nombre} {$u->apellido}")." ({$u->rol})")->implode(', '))->implode("\n"),
                );
                if (! ($respuesta['ia_disponible'] ?? false)) {
                    Log::warning('IA no disponible para cierre sombra.', ['inbound_id' => $inboundId]);

                    return;
                }
                $documentos = $respuesta['documentos'];
            } elseif (Str::contains(Str::lower(Str::ascii((string) $inbound->raw_text)), ['venta', 'qr', 'efectivo'])) {
                $documentos[] = ['tipo' => 'texto', 'texto' => ['texto' => $inbound->raw_text], 'confianza' => 1];
            }
            // La confianza no cambia la identidad; conservar la mejor lectura idéntica del inbound.
            $unicos = [];
            foreach ($documentos as $documento) {
                $datos = $this->ordenarClaves($documento[$documento['tipo']] ?? []);
                $clave = $documento['tipo'].':'.json_encode($datos, JSON_THROW_ON_ERROR);
                if (! isset($unicos[$clave]) || ($documento['confianza'] ?? 0) > ($unicos[$clave]['confianza'] ?? 0)) {
                    $unicos[$clave] = $documento;
                }
            }
            $documentos = array_values($unicos);
            $creados = DB::transaction(fn () => array_map(fn ($d) => DocumentoCierre::create([
                'inbound_id' => $inboundId, 'tipo' => $d['tipo'],
                'datos' => $d[$d['tipo']] ?? [], 'confianza' => $d['confianza'] ?? 0,
            ]), $documentos));
            foreach ($creados as $doc) {
                $this->asignador->asignar($doc);
            }
        });
    }
    private function ordenarClaves(array $datos): array
    {
        if (! array_is_list($datos)) {
            ksort($datos);
        }
        foreach ($datos as &$valor) {
            if (is_array($valor)) {
                $valor = $this->ordenarClaves($valor);
            }
        }

        return $datos;
    }

    private function calcularHuella(WhatsAppMensajeInbound $inbound): void
    {
        if ($inbound->hash_media !== null || ! $inbound->media_path) {
            return;
        }
        $ruta = collect([
            public_path($inbound->media_path),
            storage_path('app/public/'.str_replace('storage/', '', $inbound->media_path)),
        ])->first(fn ($ruta) => is_file($ruta));
        if ($ruta) {
            $inbound->hash_media = hash_file('sha256', $ruta);
            $inbound->save();
        }
    }
}
