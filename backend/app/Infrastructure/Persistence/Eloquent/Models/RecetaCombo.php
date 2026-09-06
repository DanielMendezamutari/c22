<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaCombo extends Model
{
    protected $table = 'recetas_combos';

    protected $fillable = [
        'nombre_combo',
        'producto_terminado_id',
        'unidades_equivalentes',
        'activo',
    ];

    protected $casts = [
        'unidades_equivalentes' => 'integer',
        'activo' => 'boolean',
    ];

    public function productoTerminado(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_terminado_id');
    }
}
