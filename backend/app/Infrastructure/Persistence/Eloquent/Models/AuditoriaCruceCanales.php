<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaCruceCanales extends Model
{
    protected $table = 'auditorias_cruce_canales';

    protected $fillable = [
        'mensaje_local_inbound_id',
        'mensaje_global_inbound_id',
        'sucursal_id',
        'turno_id',
        'tipo_evento',
        'monto_declarado_local',
        'monto_declarado_global',
        'diferencia_bs',
        'estado_cruce',
        'monto_definitivo_auditado',
        'imputar_responsable',
        'hash_imagen',
        'observaciones',
        'revisado_por_usuario_id',
    ];

    protected $casts = [
        'monto_declarado_local' => 'decimal:2',
        'monto_declarado_global' => 'decimal:2',
        'monto_definitivo_auditado' => 'decimal:2',
        'diferencia_bs' => 'decimal:2',
    ];

    public function mensajeLocal(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMensajeInbound::class, 'mensaje_local_inbound_id');
    }

    public function mensajeGlobal(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMensajeInbound::class, 'mensaje_global_inbound_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por_usuario_id');
    }
}
