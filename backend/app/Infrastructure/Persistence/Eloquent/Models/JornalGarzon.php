<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JornalGarzon extends Model
{
    protected $table = 'jornales_garzones';

    protected $fillable = [
        'turno_id',
        'usuario_id',
        'sucursal_id',
        'fecha',
        'jornal_base_bs',
        'faltante_botellas_unidades',
        'descuento_faltante_bs',
        'total_neto_pagado_bs',
        'foto_comprobante_url',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'jornal_base_bs' => 'decimal:2',
        'faltante_botellas_unidades' => 'decimal:2',
        'descuento_faltante_bs' => 'decimal:2',
        'total_neto_pagado_bs' => 'decimal:2',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
