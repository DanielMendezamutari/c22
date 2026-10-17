<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteCierre extends Model
{
    protected $table = 'lotes_cierre';

    protected $fillable = [
        'sucursal_id', 'fecha_operativa', 'tipo_turno', 'encargada', 'remitentes',
        'estado', 'ultimo_documento_at', 'resultado', 'conciliado_at',
    ];

    protected $casts = [
        'fecha_operativa' => 'date',
        'remitentes' => 'array',
        'resultado' => 'array',
        'ultimo_documento_at' => 'datetime',
        'conciliado_at' => 'datetime',
    ];

    public function setFechaOperativaAttribute($fecha): void
    {
        $this->attributes['fecha_operativa'] = $fecha === null ? null : Carbon::parse($fecha)->toDateString();
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoCierre::class, 'lote_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
