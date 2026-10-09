<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WhatsAppGrupoDescubierto extends Model
{
    protected $table = 'whatsapp_grupos_descubiertos';

    protected $fillable = [
        'remote_jid',
        'nombre_grupo',
        'participantes_count',
        'ultima_deteccion_at',
    ];

    protected $casts = [
        'participantes_count' => 'integer',
        'ultima_deteccion_at' => 'datetime',
    ];

    public function vinculacion(): HasOne
    {
        return $this->hasOne(SucursalWhatsAppGrupo::class, 'remote_jid', 'remote_jid');
    }
}
