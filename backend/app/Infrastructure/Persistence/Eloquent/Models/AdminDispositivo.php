<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDispositivo extends Model
{
    protected $table = 'admin_dispositivos';

    protected $fillable = [
        'usuario_id',
        'device_id',
        'fcm_token',
        'nombre_dispositivo',
        'activo',
        'ultimo_acceso',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
