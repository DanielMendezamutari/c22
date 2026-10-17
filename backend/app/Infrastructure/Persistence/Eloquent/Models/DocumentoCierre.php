<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoCierre extends Model
{
    protected $table = 'documentos_cierre';

    protected $fillable = [
        'inbound_id', 'lote_id', 'tipo', 'datos', 'confianza', 'sucursal_id',
        'fecha_operativa', 'tipo_turno', 'evidencia_casa',
    ];

    protected $casts = [
        'datos' => 'array',
        'confianza' => 'float',
        'fecha_operativa' => 'date',
    ];

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteCierre::class, 'lote_id');
    }

    public function inbound(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMensajeInbound::class, 'inbound_id');
    }
}
