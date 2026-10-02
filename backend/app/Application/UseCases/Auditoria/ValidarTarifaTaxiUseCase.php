<?php

namespace App\Application\UseCases\Auditoria;

use App\Infrastructure\Persistence\Eloquent\Models\RegistroTrasladoTaxi;
use App\Infrastructure\Persistence\Eloquent\Models\TarifaRutaTaxi;
use Carbon\Carbon;

class ValidarTarifaTaxiUseCase
{
    public function ejecutar(array $datos): RegistroTrasladoTaxi
    {
        $fechaHora = isset($datos['fecha_hora']) ? Carbon::parse($datos['fecha_hora']) : Carbon::now();
        $origenId = $datos['origen_sucursal_id'] ?? null;
        $destinoId = $datos['destino_sucursal_id'] ?? null;
        $montoCobrado = (float) ($datos['monto_cobrado_bs'] ?? 0.0);

        // 1. Consultar tarifa de referencia entre las sucursales
        $tarifaRef = 15.00;
        $tarifaMax = 20.00;

        if ($origenId && $destinoId) {
            $regla = TarifaRutaTaxi::where('origen_sucursal_id', $origenId)
                ->where('destino_sucursal_id', $destinoId)
                ->where('activo', true)
                ->first();

            if ($regla) {
                $tarifaRef = (float) $regla->tarifa_estandar_bs;
                $tarifaMax = (float) $regla->tarifa_maxima_tolerada_bs;
            }
        }

        // 2. Verificar duplicidad horaria (mismo origen/destino dentro de los últimos 30 minutos)
        $inicioVentana = $fechaHora->copy()->subMinutes(30);
        $finVentana = $fechaHora->copy()->addMinutes(30);

        $duplicado = RegistroTrasladoTaxi::whereBetween('fecha_hora', [$inicioVentana, $finVentana])
            ->where(function ($q) use ($origenId, $destinoId, $datos) {
                if ($origenId && $destinoId) {
                    $q->where('origen_sucursal_id', $origenId)
                      ->where('destino_sucursal_id', $destinoId);
                } else {
                    $q->where('origen_texto', $datos['origen_texto'] ?? '')
                      ->where('destino_texto', $datos['destino_texto'] ?? '');
                }
            })
            ->first();

        $esDuplicado = $duplicado !== null;

        // 3. Evaluar sobreprecio
        $sobreprecio = 0.00;
        $estado = 'conforme';

        if ($esDuplicado) {
            $estado = 'carrera_duplicada';
        } elseif ($montoCobrado > $tarifaMax) {
            $sobreprecio = round($montoCobrado - $tarifaRef, 2);
            $estado = 'sobreprecio_detectado';
        }

        // 4. Crear registro inmutable de auditoría de taxi
        return RegistroTrasladoTaxi::create([
            'fecha_hora' => $fechaHora,
            'origen_sucursal_id' => $origenId,
            'destino_sucursal_id' => $destinoId,
            'origen_texto' => $datos['origen_texto'] ?? 'Sucursal Origen',
            'destino_texto' => $datos['destino_texto'] ?? 'Sucursal Destino',
            'personal_trasladado' => $datos['personal_trasladado'] ?? 'Personal',
            'cantidad_pasajeros' => (int) ($datos['cantidad_pasajeros'] ?? 1),
            'monto_cobrado_bs' => $montoCobrado,
            'tarifa_referencia_bs' => $tarifaRef,
            'sobreprecio_detectado_bs' => $sobreprecio,
            'es_duplicado_horario' => $esDuplicado,
            'mensaje_original_whatsapp' => $datos['mensaje_original_whatsapp'] ?? null,
            'estado_auditoria' => $estado,
        ]);
    }
}
