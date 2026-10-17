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
            $existentes = DocumentoCierre::where('inbound_id', $inboundId)->get();
            if ($existentes->isNotEmpty()) {
                foreach ($existentes->whereNull('lote_id') as $doc) {
                    $this->asignador->asignar($doc);
                }

                return;
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
            $creados = DB::transaction(fn () => array_map(fn ($d) => DocumentoCierre::create([
                'inbound_id' => $inboundId, 'tipo' => $d['tipo'],
                'datos' => $d[$d['tipo']] ?? [], 'confianza' => $d['confianza'] ?? 0,
            ]), $documentos));
            foreach ($creados as $doc) {
                $this->asignador->asignar($doc);
            }
        });
    }
}
