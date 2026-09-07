<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Turno extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'sucursal_id',
        'barman_id',
        'tipo_turno',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'total_transformaciones_netas',
        'total_comision_bruta',
        'total_sancion_descontada',
        'total_comision_neta_pagada',
        'codigo_recibo_cobro',
        'foto_comprobante_cobro',
        'fecha_cobro',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'fecha_cobro' => 'datetime',
        'total_transformaciones_netas' => 'integer',
        'total_comision_bruta' => 'decimal:2',
        'total_sancion_descontada' => 'decimal:2',
        'total_comision_neta_pagada' => 'decimal:2',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function barman(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'barman_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'barman_id');
    }

    public function cortes(): HasMany
    {
        return $this->hasMany(CorteInventario::class, 'turno_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'turno_id');
    }

    public function auditoria(): HasOne
    {
        return $this->hasOne(AuditoriaVenta::class, 'turno_id');
    }
}
