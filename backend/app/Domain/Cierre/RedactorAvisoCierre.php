<?php

namespace App\Domain\Cierre;

use DateTimeImmutable;

final class RedactorAvisoCierre
{
    public function redactar(string $casa, string $fecha, string $turno, ?string $encargada, array $resultado, ?array $planilla, bool $actualizacion): string
    {
        $fecha = (new DateTimeImmutable($fecha))->format('d/m/Y');
        $turno = $turno === 'dia' ? 'Día' : 'Noche';
        $encargada = trim($encargada ?? '') ?: 'sin identificar';
        $lineas = [
            $actualizacion ? '🔄 *ACTUALIZACIÓN* – BORRADOR EN PRUEBA' : '🧪 *BORRADOR AUTOMÁTICO – EN PRUEBA*',
            '_Verificar contra las fotos antes de usarlo. No es un informe oficial._',
            "🏠 *{$casa}* · {$fecha} · {$turno}",
            "👤 Encargada: {$encargada}",
        ];
        foreach (['total' => 'Venta total', 'qr' => 'QR', 'linkser' => 'Linkser', 'efectivo' => 'Efectivo', 'efectivo_en_sobre' => 'Sobre'] as $clave => $etiqueta) {
            if (isset($planilla[$clave])) {
                $lineas[] = '💰 '.$etiqueta.': Bs '.$this->monto($planilla[$clave]);
            }
        }
        $lineas[] = match ($resultado['estado'] ?? null) {
            'CONFORME' => '✅ Cuadra',
            'CUADRA_PENDIENTE' => '🟡 Cuadra (comisiones se verifican al cerrar todas las casas)',
            default => '⚠️ Revisar',
        };
        $puntos = array_values(array_filter($resultado['reglas'] ?? [], fn ($regla) => in_array($regla['estado'] ?? null, ['no_cumple', 'sin_verificar'], true)));
        if ($puntos !== []) {
            $lineas[] = 'Puntos a revisar:';
            foreach (array_slice($puntos, 0, 10) as $regla) {
                $texto = $regla['detalle'] ?? 'Sin datos para verificar';
                if (isset($regla['esperado'], $regla['declarado'])) {
                    $diferencia = round((float) $regla['declarado'] - (float) $regla['esperado'], 2);
                    $texto = 'esperado Bs '.$this->monto($regla['esperado']).', declarado Bs '.$this->monto($regla['declarado'])
                        .' (dif. '.($diferencia >= 0 ? '+' : '-').$this->monto(abs($diferencia)).')';
                }
                $lineas[] = '• '.$regla['descripcion'].': '.$texto;
            }
            if (count($puntos) > 10) {
                $lineas[] = '…y '.(count($puntos) - 10).' más';
            }
        }
        $documentos = [
            'planilla_ventas' => 'planilla', 'reporte_pos' => 'reporte POS',
            'pago_chicas' => 'hoja de chicas', 'control_shampagne' => 'control de shampagne',
            'sobre' => 'foto del sobre', 'comprobante_transferencia' => 'comprobante QR',
        ];
        if (! empty($resultado['documentos_faltantes'])) {
            $lineas[] = 'Falta enviar: '.implode(', ', array_map(fn ($tipo) => $documentos[$tipo] ?? str_replace('_', ' ', $tipo), $resultado['documentos_faltantes']));
        }
        foreach (array_slice($resultado['alertas'] ?? [], 0, 5) as $alerta) {
            $lineas[] = 'ℹ️ '.$alerta;
        }

        // Los textos de auditoría también pueden incluir términos acusatorios.
        return preg_replace('/\b(faltante|robo|pérdida)\b/iu', 'dato por revisar', implode("\n", $lineas));
    }

    private function monto(float|int|string $monto): string
    {
        return number_format((float) $monto, 2, ',', '.');
    }
}
