<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMensajeOutbound extends Model
{
    protected $table = 'whatsapp_mensajes_outbound';

    protected $fillable = [
        'remote_jid',
        'sucursal_id',
        'turno_id',
        'tipo_mensaje',
        'contenido_texto',
        'media_url',
        'estado_envio',
        'whatsapp_message_id',
        'error_motivo',
        'despachado_at',
    ];

    protected $casts = [
        'despachado_at' => 'datetime',
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
