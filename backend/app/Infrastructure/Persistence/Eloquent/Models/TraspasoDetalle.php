<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraspasoDetalle extends Model
{
    protected $table = 'traspasos_detalles';

    protected $fillable = [
        'traspaso_id',
        'producto_id',
        'cantidad_despachada',
        'cantidad_recibida_conforme',
        'cantidad_merma_transito',
    ];

    protected $casts = [
        'cantidad_despachada' => 'decimal:2',
        'cantidad_recibida_conforme' => 'decimal:2',
        'cantidad_merma_transito' => 'decimal:2',
    ];

    public function traspaso(): BelongsTo
    {
        return $this->belongsTo(Traspaso::class, 'traspaso_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
