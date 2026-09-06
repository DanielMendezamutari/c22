<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaDiscrepancia extends Model
{
    protected $table = 'alertas_discrepancias';

    protected $fillable = [
        'sucursal_id',
        'turno_saliente_id',
        'turno_entrante_id',
        'producto_id',
        'stock_esperado',
        'stock_declarado',
        'diferencia',
        'resuelto',
        'observaciones',
        'fecha_alerta',
    ];

    protected $casts = [
        'stock_esperado' => 'decimal:2',
        'stock_declarado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'resuelto' => 'boolean',
        'fecha_alerta' => 'datetime',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function turnoSaliente(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_saliente_id');
    }

    public function turnoEntrante(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_entrante_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
