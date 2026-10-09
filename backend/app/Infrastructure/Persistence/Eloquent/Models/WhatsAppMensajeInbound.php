<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMensajeInbound extends Model
{
    protected $table = 'whatsapp_mensajes_inbound';

    protected $fillable = [
        'sucursal_id',
        'remote_jid',
        'sender_phone',
        'sender_name',
        'tipo_mensaje',
        'media_path',
        'raw_text',
        'clasificacion_ia',
        'score_confianza',
        'metadata_ia',
        'estado',
        'turno_id',
    ];

    protected $casts = [
        'score_confianza' => 'decimal:2',
        'metadata_ia' => 'array',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }
}
