<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SancionInventario extends Model
{
    protected $table = 'sanciones_inventario';

    protected $fillable = [
        'usuario_id',
        'auditoria_id',
        'monto_sancion',
        'monto_descontado',
        'estado',
        'fecha_imputacion',
        'fecha_liquidacion',
    ];

    protected $casts = [
        'monto_sancion' => 'decimal:2',
        'monto_descontado' => 'decimal:2',
        'fecha_imputacion' => 'datetime',
        'fecha_liquidacion' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(AuditoriaVenta::class, 'auditoria_id');
    }
}
