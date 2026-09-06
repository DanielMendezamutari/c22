<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traspaso extends Model
{
    protected $table = 'traspasos';

    protected $fillable = [
        'sucursal_origen_id',
        'sucursal_destino_id',
        'producto_id',
        'cantidad_despachada',
        'cantidad_recibida_conforme',
        'cantidad_merma_transito',
        'estado',
        'usuario_emisor_id',
        'usuario_receptor_id',
        'foto_despacho',
        'foto_recepcion',
        'fecha_envio',
        'fecha_recepcion',
    ];

    protected $casts = [
        'cantidad_despachada' => 'decimal:2',
        'cantidad_recibida_conforme' => 'decimal:2',
        'cantidad_merma_transito' => 'decimal:2',
        'fecha_envio' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuarioEmisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_emisor_id');
    }

    public function usuarioReceptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_receptor_id');
    }

    public function detalles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TraspasoDetalle::class, 'traspaso_id');
    }
}
