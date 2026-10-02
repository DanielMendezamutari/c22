<?php

namespace App\Infrastructure\AI;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaxiParserService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->model = env('GEMINI_MODEL', 'gemini-1.5-flash');
    }

    /**
     * Parsea un mensaje de texto de WhatsApp sobre rotación de chicas / carreras de taxi con Gemini NLP.
     */
    public function parsearMensajeTaxi(string $mensaje): array
    {
        $mensajeLimpio = trim($mensaje);

        // Si hay API key de Gemini, usar comprensión de lenguaje natural
        if (!empty($this->apiKey)) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

                $prompt = <<<PROMPT
Analiza el siguiente mensaje de WhatsApp enviado en un grupo nocturno sobre carreras de taxi y traslados de personal/chicas entre locales nocturnos (Casa22, Casa Coron, Madan).
Mensaje: "{$mensajeLimpio}"

Extrae con precisión en formato JSON estricto:
1. "origen_texto": Nombre del local o punto de partida (ej: "Casa22", "Casa Coron", "Madan" o dirección).
2. "destino_texto": Nombre del local o punto de destino.
3. "personal_trasladado": Nombres o alias de las personas trasladadas (ej: "Sofia, Maria").
4. "cantidad_pasajeros": Número estimado de pasajeros (por defecto 1 si no se especifica).
5. "monto_cobrado_bs": Importe numérico cobrado por la carrera en Bolivianos (Bs).

Responde ÚNICAMENTE el objeto JSON sin bloques de código ni texto adicional.
PROMPT;

                $response = Http::timeout(10)->post($endpoint, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
                    $data = json_decode($clean, true);

                    if (is_array($data) && isset($data['monto_cobrado_bs'])) {
                        return $this->resolverSucursales($data, $mensajeLimpio);
                    }
                }
            } catch (Throwable $e) {
                Log::warning("TaxiParserService: Fallback a regex por error en Gemini: {$e->getMessage()}");
            }
        }

        // Fallback de RegEx y heurística para pruebas locales y mensajes estándar
        return $this->parsearConHeuristica($mensajeLimpio);
    }

    private function parsearConHeuristica(string $msg): array
    {
        $lower = strtolower($msg);

        // Extraer monto en Bs (ej. "30 bs", "20bs", "bs 15", "40 bolivianos")
        $monto = 15.00;
        if (preg_match('/(?:bs\.?|bolivianos?)\s*(\d+(?:\.\d+)?)/i', $msg, $m) ||
            preg_match('/(\d+(?:\.\d+)?)\s*(?:bs\.?|bolivianos?)/i', $msg, $m)) {
            $monto = (float) $m[1];
        }

        // Detectar origen y destino
        $origen = 'Casa22';
        $destino = 'Madan';

        if (str_contains($lower, 'coron') && str_contains($lower, 'madan')) {
            if (strpos($lower, 'coron') < strpos($lower, 'madan')) {
                $origen = 'Casa Coron';
                $destino = 'Madan';
            } else {
                $origen = 'Madan';
                $destino = 'Casa Coron';
            }
        } elseif (str_contains($lower, '22') && str_contains($lower, 'coron')) {
            if (strpos($lower, '22') < strpos($lower, 'coron')) {
                $origen = 'Casa22';
                $destino = 'Casa Coron';
            } else {
                $origen = 'Casa Coron';
                $destino = 'Casa22';
            }
        } elseif (str_contains($lower, '22') && str_contains($lower, 'madan')) {
            if (strpos($lower, '22') < strpos($lower, 'madan')) {
                $origen = 'Casa22';
                $destino = 'Madan';
            } else {
                $origen = 'Madan';
                $destino = 'Casa22';
            }
        }

        return $this->resolverSucursales([
            'origen_texto' => $origen,
            'destino_texto' => $destino,
            'personal_trasladado' => 'Personal en rotación',
            'cantidad_pasajeros' => 1,
            'monto_cobrado_bs' => $monto,
        ], $msg);
    }

    private function resolverSucursales(array $data, string $originalMsg): array
    {
        $sucursales = Sucursal::all(['id', 'nombre']);

        $origenId = null;
        $destinoId = null;

        foreach ($sucursales as $s) {
            if (stripos($data['origen_texto'] ?? '', $s->nombre) !== false ||
                (stripos($data['origen_texto'] ?? '', '22') !== false && str_contains($s->nombre, '22')) ||
                (stripos($data['origen_texto'] ?? '', 'coron') !== false && str_contains($s->nombre, 'Coron')) ||
                (stripos($data['origen_texto'] ?? '', 'madan') !== false && str_contains($s->nombre, 'Madan'))) {
                $origenId = $s->id;
            }

            if (stripos($data['destino_texto'] ?? '', $s->nombre) !== false ||
                (stripos($data['destino_texto'] ?? '', '22') !== false && str_contains($s->nombre, '22')) ||
                (stripos($data['destino_texto'] ?? '', 'coron') !== false && str_contains($s->nombre, 'Coron')) ||
                (stripos($data['destino_texto'] ?? '', 'madan') !== false && str_contains($s->nombre, 'Madan'))) {
                $destinoId = $s->id;
            }
        }

        $data['origen_sucursal_id'] = $origenId;
        $data['destino_sucursal_id'] = $destinoId;
        $data['mensaje_original_whatsapp'] = $originalMsg;

        return $data;
    }
}
