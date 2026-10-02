<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaRutaTaxi extends Model
{
    protected $table = 'tarifas_rutas_taxis';

    protected $fillable = [
        'origen_sucursal_id',
        'destino_sucursal_id',
        'tarifa_estandar_bs',
        'tarifa_maxima_tolerada_bs',
        'activo',
    ];

    protected $casts = [
        'tarifa_estandar_bs' => 'decimal:2',
        'tarifa_maxima_tolerada_bs' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'origen_sucursal_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'destino_sucursal_id');
    }
}
