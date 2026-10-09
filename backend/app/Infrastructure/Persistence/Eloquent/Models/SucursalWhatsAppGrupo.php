<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SucursalWhatsAppGrupo extends Model
{
    protected $table = 'sucursal_whatsapp_grupos';

    protected $fillable = [
        'sucursal_id',
        'remote_jid',
        'nombre_grupo',
        'tipo_auditoria',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
