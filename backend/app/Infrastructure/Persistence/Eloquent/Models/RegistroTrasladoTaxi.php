<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroTrasladoTaxi extends Model
{
    protected $table = 'registros_traslados_taxis';

    protected $fillable = [
        'fecha_hora',
        'origen_sucursal_id',
        'destino_sucursal_id',
        'origen_texto',
        'destino_texto',
        'personal_trasladado',
        'cantidad_pasajeros',
        'monto_cobrado_bs',
        'tarifa_referencia_bs',
        'sobreprecio_detectado_bs',
        'es_duplicado_horario',
        'mensaje_original_whatsapp',
        'estado_auditoria',
        'aprobado_por_id',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'monto_cobrado_bs' => 'decimal:2',
        'tarifa_referencia_bs' => 'decimal:2',
        'sobreprecio_detectado_bs' => 'decimal:2',
        'es_duplicado_horario' => 'boolean',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'origen_sucursal_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'destino_sucursal_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por_id');
    }
}
