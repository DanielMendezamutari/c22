<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorteInventario extends Model
{
    protected $table = 'cortes_inventario';
    public $timestamps = false;

    protected $fillable = [
        'turno_id',
        'producto_id',
        'tipo_corte',
        'cantidad',
        'created_at',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
