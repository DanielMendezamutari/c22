<?php

namespace App\Services;

use App\Infrastructure\AI\GeminiVisionService;
use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiVisionAuditorService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private GeminiVisionService $baseVisionService;

    public function __construct(GeminiVisionService $baseVisionService)
    {
        $this->baseVisionService = $baseVisionService;
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    }

    /**
     * Clasifica autónomamente y extrae información contable estructurada de cualquier comprobante
     * recibido desde WhatsApp o subida manual.
     *
     * @param string $imagePathOrBase64 Ruta local o Base64 del archivo
     * @param string|null $mimetype
     * @return array{
     *   clasificacion: 'planilla_caja'|'ticket_pos'|'voucher_deposito'|'recibo_gasto'|'otro',
     *   score_confianza: float,
     *   sucursal_sugerida: string|null,
     *   datos: array
     * }
     */
    public function auditarYClasificar(string $imagePathOrBase64, ?string $mimetype = 'image/jpeg', ?string $contextoTexto = null): array
    {
        $base64 = $this->obtenerBase64($imagePathOrBase64);
        if (!$base64) {
            return [
                'clasificacion' => 'otro',
                'score_confianza' => 0.00,
                'sucursal_sugerida' => null,
                'ia_disponible' => false,
                'datos' => ['error' => 'No se pudo leer la imagen o archivo'],
            ];
        }

        // Obtener nombres de las casas comerciales activas de la base de datos
        $casasNombres = Sucursal::where('activo', true)->pluck('nombre')->toArray();
        $casasTexto = !empty($casasNombres) ? implode(', ', $casasNombres) : 'Casa Belén / C22, Casa Corona, Madan, Cuarta Casa';

        // Si no hay API key configurada, retornar simulación heurística
        if (empty($this->apiKey)) {
            Log::info("GeminiVisionAuditorService: Modo heurístico activo (sin GEMINI_API_KEY).");
            return $this->simularClasificacion($imagePathOrBase64);
        }

        $personalTexto = $this->construirPersonalPorCasa();

        $contextoMensajePrompt = !empty($contextoTexto)
            ? "\nCONTEXTO / TEXTO ADJUNTO EN WHATSAPP POR EL REMITENTE:\n\"{$contextoTexto}\"\n(IMPORTANTE: Utiliza este texto para interpretar el motivo del gasto, el taxi, almuerzo, o la sucursal de origen si el remitente lo indica).\n"
            : "";

        $prompt = <<<PROMPT
Eres el Auditor Contable Senior con Inteligencia Artificial de Grupo Punto Frío (operando las casas comerciales: {$casasTexto} en Bolivia).
Analiza esta imagen enviada por WhatsApp al grupo de recaudación, gastos de caja chica o cierres de turno.
{$contextoMensajePrompt}
Realiza las siguientes 3 tareas:
1. CLASIFICACIÓN RIGUROSA DE DOCUMENTOS:
   - "planilla_caja": ÚNICAMENTE la hoja principal de rendición/cierre de caja del turno (hoja de cuaderno rayado/cuadriculado o formato impreso rellenado a mano donde se anotan las ventas totales de la noche, cobros QR, pagos a chicas, egresos y efectivo entregado en sobre).
   - "ticket_pos": Ticket térmico estrecho impreso de ventas o reporte de corte de máquina.
   - "voucher_deposito": Capturas de transferencias o depósitos bancarios de RECAUDACIÓN a cuentas de la empresa (cuentas fiscales/gerencia).
   - "recibo_gasto": Recibos físicos de gastos (taxis, hielo, insumos) O capturas de pantalla de pagos QR/transferencias de GASTOS menores de caja chica (ej. pago de taxi por QR, comisión de traslado, almuerzo del personal, compras de emergencia). NUNCA clasifiques un gasto como planilla_caja.
   - "cierre_barra": Conteo de productos / inventario del barman (lista de botellas, latas, unidades o stock, formulario de conteo de la app o acta de conteo). Es control de inventario: NUNCA es "planilla_caja" aunque diga "cierre".
   - "otro": Fotografías de personas, botellas, memes o imágenes no contables.

2. DETECCIÓN INTELIGENTE DE CASA / SUCURSAL Y PERSONAL (REGLA CRÍTICA):
En Grupo Punto Frío operan las casas comerciales: {$casasTexto}. Debes identificar a cuál de las sedes pertenece el documento inspeccionando membrete, notas manuscritas, cajeros y personal:
   - Pistas de membrete conocidas:
     * "SERVIDOR: BELEN CASA22", "BELEN", "C22", "Casa 22", "CASA22" → casa Belén / Casa22.
     * "CORONA", "CASA CORONA", "CORON" → casa Corona.
     * "MADAN", "MADAM", "MADAMES" → Madan.
     * "VIP", "C22VIP", "ADM VIP", "PRIVADO" → Casa 22 VIP.
     * "CASA 4", "CASA NUEVA", "EL JEFE", "JOKER" → Cuarta Casa.
   - PERSONAL REGISTRADO POR CASA (fuente oficial; el personal rota y hay varias encargadas por turno):
{$personalTexto}
   - Responde en sucursal_sugerida el NOMBRE EXACTO de una casa de la lista anterior.
   - Si no hay suficiente información para determinar la sucursal, indica null en sucursal_sugerida.

3. EXTRACCIÓN DE DATOS Y REGLAS DE NEGOCIO:
   - es_cierre_turno: true si la imagen representa el cierre de turno / corte final de caja (planilla de rendición de caja o corte general del POS). false si es un gasto menor o comprobante aislado.
   - DISTINCIÓN DE PERSONAL: NUNCA confundas a barmans o garzones con cajeros. Las personas listadas como barmans/garzones en PERSONAL REGISTRADO no son encargadas; cualquier monto anotado a su nombre es un egreso (pago de turno, adelanto o comisión de barra), JAMÁS el total de ventas ni el nombre del cajero.
   - REGLAS DE COMISIONES Y DEDUCCIONES:
     * Pago a chicas: 50% de las bebidas de compañía, 60% si hay pieza (la casa se queda con el 40%).
     * Deducción de limpieza: Si una chica genera >= 100 Bs, se le descuenta 10 Bs de limpieza, lo cual entra como ingreso de la casa.
     * Ventas sin comandar: Si en la planilla o sobre figura notas como "Wisk 80 sin comandar" o bebidas no comandadas en POS, regístralo explícitamente y reporta la fuga.
   - Si es "ticket_pos":
     * ventas_efectivo_bs: monto cobrado en efectivo según el POS.
     * ventas_qr_bs: monto cobrado en QR / transferencias según el POS.
     * total_cobros_bs: total cobrado por el POS.
     * fecha_hora: fecha y hora de emisión del ticket.
     * servidor_cajero: nombre del cajero o servidor (preferir una encargada de PERSONAL REGISTRADO si coincide).
     * items_vendidos_count: cantidad total de ítems.
     * es_cierre_turno: true si es reporte de corte Z, cierre de caja o resumen diario; false si es comanda simple.
   - Si es "planilla_caja":
     * es_cierre_turno: true (siempre representa el cierre del turno).
     * total_ingresos_bs: suma total de ingresos declarados.
     * total_ventas_declaradas_bs: total ventas de la noche/día.
     * ventas_bebidas_bs: total ventas bebidas.
     * shampagne_ingreso_bs: total ingreso por champagne.
     * caja_chica_inicial_bs: monto inicial de caja chica.
     * total_egresos_bs: suma de todos los egresos / gastos.
     * total_gastos_declarados_bs: suma de egresos.
     * pago_chicas_bs: total pagado a chicas.
     * pago_garzones_bs: total pagado a garzones.
     * pago_personal_bs: total pagado a personal (barmans, garzones, limpieza).
     * gastos_operativos_bs: suma de hielo, taxis, compras.
     * qr_declarado_bs: monto declarado como cobrado o pagado por QR.
     * monto_sobre_efectivo_bs: efectivo físico entregado en sobre.
     * cajero_nombre: nombre de la encargada que rinde, tal como figura en la planilla (preferir la grafía de PERSONAL REGISTRADO si coincide).
     * fecha: fecha del turno (formato YYYY-MM-DD).
     * turno: "Día" o "Noche" (si dice "Noche" o se envía entre las 18:00 y las 06:00, asignar "Noche"; si es diurno asignar "Día").
     * ventas_sin_comandar_bs: monto en Bs de bebidas sin comandar si está anotado en sobre/planilla (ej. 80 para whisky sin comandar).
   - Si es "voucher_deposito": Extrae banco_nombre, monto_depositado_bs, nro_operacion, fecha_hora, titular_cuenta.
   - Si es "recibo_gasto": Extrae concepto (ej: "Comisión taxi", "Almuerzo", "Compra de hielo"), categoria ("taxi_personal", "hielo", "insumos_barra", "limpieza", "otros"), monto_bs, proveedor, fecha, nro_operacion, banco_nombre.

Responde ÚNICAMENTE un JSON con este formato exacto:
{
  "clasificacion": "planilla_caja" | "ticket_pos" | "voucher_deposito" | "recibo_gasto" | "cierre_barra" | "otro",
  "score_confianza": 0.95,
  "sucursal_sugerida": "<nombre exacto de una casa de la lista>" | null,
  "datos": { ...campos extraidos... }
}
PROMPT;

        $modelos = array_unique([$this->model ?: 'gemini-3.5-flash-lite', 'gemini-3.5-flash-lite', 'gemini-flash-latest']);

        foreach ($modelos as $modeloActual) {
            try {
                $endpoint = "{$this->baseUrl}{$modeloActual}:generateContent?key={$this->apiKey}";

                $response = Http::withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                ])->timeout(30)->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimetype ?: 'image/jpeg',
                                        'data' => $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                if ($response->successful()) {
                    $parts = $response->json('candidates.0.content.parts', []);
                    $jsonText = '';
                    foreach ($parts as $p) {
                        if (!empty($p['text'])) {
                            $jsonText = $p['text'];
                        }
                    }

                    $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($jsonText));
                    $decoded = json_decode($cleanJson, true);

                    if (is_array($decoded) && isset($decoded['clasificacion'])) {
                        return [
                            'clasificacion' => $decoded['clasificacion'],
                            'score_confianza' => (float) ($decoded['score_confianza'] ?? 0.85),
                            'sucursal_sugerida' => $decoded['sucursal_sugerida'] ?? null,
                            'datos' => $decoded['datos'] ?? [],
                        ];
                    }
                } else {
                    Log::warning("Gemini Vision ({$modeloActual}) Error (" . $response->status() . "): " . $response->body() . ". Probando siguiente modelo...");
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini Vision ({$modeloActual}) Excepción: " . $e->getMessage() . ". Probando siguiente modelo...");
            }
        }

        return $this->simularClasificacion($imagePathOrBase64);
    }

    /**
     * Concilia un lote de documentos fotografiados (Tickets POS + Planillas Manuscritas)
     * cruzándolos contra el texto acompañante de WhatsApp en modalidad "Solo WhatsApp".
     *
     * @param array $loteOcr Array de resultados de auditarYClasificar
     * @param string $textoWhatsapp Texto plano enviado en el chat
     * @return array
     */
    public function conciliarLoteCierre(array $loteOcr, string $textoWhatsapp = ''): array
    {
        $ticketPos = null;
        $planillaGeneral = null;
        $planillaChampagne = null;
        $planillaChicas = null;

        foreach ($loteOcr as $item) {
            $tipo = $item['clasificacion'] ?? 'otro';
            $datos = $item['datos'] ?? [];

            if ($tipo === 'ticket_pos') {
                $ticketPos = $datos;
            } elseif ($tipo === 'planilla_caja') {
                if (isset($datos['total_ingresos_bs']) || isset($datos['total_ventas_declaradas_bs'])) {
                    $planillaGeneral = $datos;
                } elseif (isset($datos['shampagne_ingreso_bs'])) {
                    $planillaChampagne = $datos;
                } elseif (isset($datos['pago_chicas_bs'])) {
                    $planillaChicas = $datos;
                } else {
                    $planillaGeneral = $datos;
                }
            }
        }

        // Parsear números del texto de WhatsApp
        $datosTexto = $this->extraerDatosTextoWhatsapp($textoWhatsapp);

        $ventasPos = (float) ($ticketPos['total_cobros_bs'] ?? $ticketPos['total_ticket_bs'] ?? 0.00);
        $qrPos = (float) ($ticketPos['ventas_qr_bs'] ?? $ticketPos['qr_pos_bs'] ?? 0.00);
        $efectivoPos = (float) ($ticketPos['ventas_efectivo_bs'] ?? $ticketPos['efectivo_pos_bs'] ?? 0.00);

        $ventasPlanilla = (float) ($planillaGeneral['total_ingresos_bs'] ?? $planillaGeneral['total_ventas_declaradas_bs'] ?? 0.00);
        $egresosPlanilla = (float) ($planillaGeneral['total_egresos_bs'] ?? $planillaGeneral['total_gastos_declarados_bs'] ?? 0.00);
        $qrPlanilla = (float) ($planillaGeneral['qr_declarado_bs'] ?? 0.00);
        $sobrePlanilla = (float) ($planillaGeneral['monto_sobre_efectivo_bs'] ?? 0.00);

        $qrTexto = $datosTexto['qr_bs'] ?? null;
        $ventasTexto = $datosTexto['venta_total_bs'] ?? null;
        $sobreTexto = $datosTexto['efectivo_bs'] ?? null;

        $alertas = [];

        // Alerta 1: Texto de WhatsApp vs Planilla física
        if ($qrTexto !== null && $qrPlanilla > 0 && abs($qrTexto - $qrPlanilla) >= 1.00) {
            $difTexto = abs($qrTexto - $qrPlanilla);
            $alertas[] = [
                'nivel' => 'CRITICO',
                'tipo' => 'error_tipeo_whatsapp',
                'diferencia_bs' => $difTexto,
                'titulo' => 'Inconsistencia Texto WhatsApp vs Planilla Física',
                'descripcion' => sprintf(
                    'En el mensaje de WhatsApp se escribió Qr: %.2f Bs, pero en la planilla física manuscrita dice QR: %.2f Bs (Diferencia de %.2f Bs).',
                    $qrTexto,
                    $qrPlanilla,
                    $difTexto
                ),
            ];
        }

        // Alerta 2: Cruce POS vs Planilla en QR
        if ($qrPos > 0 && $qrPlanilla > 0 && abs($qrPos - $qrPlanilla) >= 1.00) {
            $alertas[] = [
                'nivel' => 'CRITICO',
                'tipo' => 'flujo_fondos_qr',
                'titulo' => 'Discrepancia en Cobros QR (POS vs Caja)',
                'descripcion' => sprintf(
                    'En el sistema POS se cobraron %.2f Bs por QR bancario, mientras que en la planilla solo se declararon %.2f Bs de QR restando los egresos del efectivo.',
                    $qrPos,
                    $qrPlanilla
                ),
            ];
        }

        // Alerta 3: Ventas POS vs Planilla
        if ($ventasPos > 0 && $ventasPlanilla > 0 && abs($ventasPos - $ventasPlanilla) >= 1.00) {
            $difVentas = $ventasPlanilla - $ventasPos;
            $alertas[] = [
                'nivel' => 'ADVERTENCIA',
                'tipo' => 'diferencia_ventas',
                'titulo' => 'Diferencia en Ventas Totales',
                'descripcion' => sprintf(
                    'Planilla declara %.2f Bs y el POS cobró %.2f Bs (Diferencia de %+.2f Bs, posible cobro manual no ingresado al POS).',
                    $ventasPlanilla,
                    $ventasPos,
                    $difVentas
                ),
            ];
        }

        return [
            'ticket_pos' => $ticketPos,
            'planilla_general' => $planillaGeneral,
            'texto_whatsapp' => $datosTexto,
            'ventas_pos' => $ventasPos,
            'qr_pos' => $qrPos,
            'efectivo_pos' => $efectivoPos,
            'ventas_planilla' => $ventasPlanilla,
            'egresos_planilla' => $egresosPlanilla,
            'qr_planilla' => $qrPlanilla,
            'sobre_planilla' => $sobrePlanilla,
            'alertas' => $alertas,
            'conforme' => count($alertas) === 0,
            'cuadre_conforme' => count($alertas) === 0,
        ];
    }

    private function extraerDatosTextoWhatsapp(string $texto): array
    {
        $datos = [];

        // Venta total:32690
        if (preg_match('/(?:venta\s*total|total\s*venta)[\s:]*([0-9.,]+)/i', $texto, $m)) {
            $datos['venta_total_bs'] = (float) str_replace(',', '', $m[1]);
        }
        // Qr:14448
        if (preg_match('/(?:qr|banco)[\s:]*([0-9.,]+)/i', $texto, $m)) {
            $datos['qr_bs'] = (float) str_replace(',', '', $m[1]);
        }
        // Efectivo:1510
        if (preg_match('/(?:efectivo|sobre)[\s:]*([0-9.,]+)/i', $texto, $m)) {
            $datos['efectivo_bs'] = (float) str_replace(',', '', $m[1]);
        }

        return $datos;
    }

    private function obtenerBase64(string $pathOrData): ?string
    {
        if (str_starts_with($pathOrData, 'data:')) {
            $parts = explode(',', $pathOrData);
            return $parts[1] ?? null;
        }

        if (file_exists($pathOrData)) {
            $content = file_get_contents($pathOrData);
            return $content ? base64_encode($content) : null;
        }

        $fullPath = storage_path('app/public/' . str_replace('storage/', '', $pathOrData));
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            return $content ? base64_encode($content) : null;
        }

        $appPath = storage_path('app/' . $pathOrData);
        if (file_exists($appPath)) {
            $content = file_get_contents($appPath);
            return $content ? base64_encode($content) : null;
        }

        if (base64_decode($pathOrData, true) !== false) {
            return $pathOrData;
        }

        return null;
    }

    /**
     * Respuesta cuando la IA no está disponible: nunca inventa cifras (spec 004 FR-006).
     */
    private function simularClasificacion(string $pathOrData): array
    {
        return [
            'clasificacion' => 'otro',
            'score_confianza' => 0.00,
            'sucursal_sugerida' => null,
            'ia_disponible' => false,
            'datos' => ['error' => 'IA no disponible o sin respuesta válida; requiere revisión manual'],
        ];
    }

    /**
     * Personal activo por casa, para que la IA distinga encargadas de barmans sin nombres fijos en código.
     */
    private function construirPersonalPorCasa(): string
    {
        $personal = Usuario::where('activo', true)
            ->whereIn('rol', ['cajera', 'barman', 'garzon'])
            ->whereNotNull('sucursal_actual_id')
            ->get(['nombre', 'apellido', 'rol', 'sucursal_actual_id'])
            ->groupBy('sucursal_actual_id');

        $lineas = [];
        foreach (Sucursal::where('activo', true)->get(['id', 'nombre', 'codigo']) as $casa) {
            $deCasa = $personal->get($casa->id, collect());
            $nombres = fn (array $roles) => $deCasa->whereIn('rol', $roles)
                ->map(fn ($u) => trim($u->nombre . ' ' . $u->apellido))->unique()->implode(', ');
            $encargadas = $nombres(['cajera']) ?: 'sin registrar (leer de la planilla)';
            $barra = $nombres(['barman', 'garzon']) ?: 'sin registrar';
            $lineas[] = "     * \"{$casa->nombre}\" (código {$casa->codigo}): Encargadas/cajeras: {$encargadas}. Barmans/garzones (NO encargados): {$barra}.";
        }

        return $lineas ? implode("\n", $lineas) : '     * (sin personal registrado)';
    }
}
