<?php

namespace App\Infrastructure\AI;

use App\Infrastructure\Persistence\Eloquent\Models\GastoCajaChica;
use App\Infrastructure\Persistence\Eloquent\Models\PlanillaCaja;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuditoriaGastosService
{
    private GeminiVisionService $geminiService;

    public function __construct(GeminiVisionService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Cruza los gastos manuscritos de la planilla contra los recibos fotográficos enviados a WhatsApp o cargados en la web.
     */
    public function cruzarGastosConPlanilla(int $sucursalId, string $fecha): array
    {
        $fechaObj = Carbon::parse($fecha)->toDateString();

        // 1. Obtener la planilla manuscrita del día
        $planilla = PlanillaCaja::where('sucursal_id', $sucursalId)
            ->where('fecha_operativa', $fechaObj)
            ->latest('id')
            ->first();

        $gastosManuscritos = [];
        if ($planilla && !empty($planilla->datos_ocr_json['gastos_desglosados'])) {
            $gastosManuscritos = $planilla->datos_ocr_json['gastos_desglosados'];
        }

        // 2. Obtener gastos ya registrados en la base de datos
        $gastosExistentes = GastoCajaChica::where('sucursal_id', $sucursalId)
            ->where('fecha', $fechaObj)
            ->get();

        $gastosProcesados = [];

        // Si hay desglose de la planilla, asegurar que cada uno exista en gastos_caja_chica
        foreach ($gastosManuscritos as $item) {
            $concepto = trim($item['concepto'] ?? 'Gasto general');
            $monto = (float) ($item['monto_bs'] ?? 0.0);

            // Buscar si ya tiene comprobante fotográfico cargado
            $gastoMatch = $gastosExistentes->first(function ($g) use ($concepto, $monto) {
                return (abs((float)$g->monto_bs - $monto) < 1.0) || stripos($g->concepto, $concepto) !== false;
            });

            if (!$gastoMatch) {
                // Se anotó a mano en planilla pero NO tiene foto de recibo -> OBSERVADO_SIN_COMPROBANTE
                $categoria = $this->clasificarCategoria($concepto);
                $nuevoGasto = GastoCajaChica::create([
                    'sucursal_id' => $sucursalId,
                    'turno_id' => $planilla->turno_id,
                    'planilla_id' => $planilla->id,
                    'fecha' => $fechaObj,
                    'concepto' => $concepto,
                    'categoria' => $categoria,
                    'monto_bs' => $monto,
                    'es_reposicion_de_ventas' => true,
                    'foto_comprobante_url' => null,
                    'estado_comprobante' => 'observado_sin_comprobante',
                    'emparejado_ocr' => false,
                    'observaciones' => 'Anotado en planilla física de caja pero sin comprobante fotográfico de respaldo.',
                ]);
                $gastosProcesados[] = $nuevoGasto;
            } else {
                // Ya tiene registro: si tiene foto marcar como aprobado
                if (!empty($gastoMatch->foto_comprobante_url) && $gastoMatch->estado_comprobante === 'pendiente_revision') {
                    $gastoMatch->update([
                        'estado_comprobante' => 'aprobado_con_foto',
                        'emparejado_ocr' => true,
                        'planilla_id' => $planilla->id,
                    ]);
                }
                $gastosProcesados[] = $gastoMatch;
            }
        }

        return [
            'total_gastos' => count($gastosProcesados),
            'gastos' => $gastosProcesados,
            'total_monto_bs' => array_sum(array_map(fn($g) => (float)$g->monto_bs, $gastosProcesados)),
            'total_observados_sin_foto' => count(array_filter($gastosProcesados, fn($g) => $g->estado_comprobante === 'observado_sin_comprobante')),
        ];
    }

    private function clasificarCategoria(string $concepto): string
    {
        $c = strtolower($concepto);
        if (str_contains($c, 'hielo')) return 'hielo';
        if (str_contains($c, 'taxi') || str_contains($c, 'carrera') || str_contains($c, 'chicas')) return 'taxi_personal';
        if (str_contains($c, 'limpieza') || str_contains($c, 'detergente') || str_contains($c, 'bolsa')) return 'limpieza';
        if (str_contains($c, 'vaso') || str_contains($c, 'bombilla') || str_contains($c, 'limon')) return 'insumos_barra';
        if (str_contains($c, 'luz') || str_contains($c, 'foco') || str_contains($c, 'plomero')) return 'mantenimiento';
        return 'otros';
    }
}
