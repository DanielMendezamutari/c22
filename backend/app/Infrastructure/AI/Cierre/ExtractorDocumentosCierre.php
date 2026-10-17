<?php

namespace App\Infrastructure\AI\Cierre;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ExtractorDocumentosCierre
{
    private readonly string $apiKey;
    private readonly string $modelo;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->modelo = (string) config('services.gemini.model', '');
    }

    public function extraer(string $rutaOBase64, string $mime, string $contexto = '', string $casas = '', string $personal = ''): array
    {
        if (trim($this->apiKey) === '') {
            return $this->fallo('No hay API key de Gemini configurada.');
        }

        try {
            $datos = $this->leerDocumento($rutaOBase64);
        } catch (Throwable) {
            return $this->fallo('No se pudo leer el documento.');
        }

        $esquema = EsquemaDocumentosCierre::responseSchema();
        $modelos = array_unique(array_filter([$this->modelo, 'gemini-3.5-flash-lite', 'gemini-flash-latest']));
        $error = 'Ningún modelo devolvió documentos válidos.';

        foreach ($modelos as $modelo) {
            try {
                $respuesta = Http::withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                    ->timeout(60)
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($modelo).':generateContent?key='.rawurlencode($this->apiKey), [
                        'contents' => [['parts' => [
                            ['text' => EsquemaDocumentosCierre::prompt($casas, $personal, $contexto)],
                            ['inline_data' => ['mime_type' => $mime, 'data' => $datos]],
                        ]]],
                        'generationConfig' => [
                            'temperature' => 0,
                            'responseMimeType' => 'application/json',
                            'responseSchema' => $esquema,
                        ],
                    ]);

                if (! $respuesta->successful()) {
                    throw new RuntimeException('Gemini respondió HTTP '.$respuesta->status().'.');
                }

                $texto = '';
                foreach ($respuesta->json('candidates.0.content.parts', []) as $parte) {
                    $texto .= $parte['text'] ?? '';
                }
                $resultado = json_decode($texto, false, 512, JSON_THROW_ON_ERROR);
                if (! is_object($resultado) || ! isset($resultado->documentos) || ! is_array($resultado->documentos)) {
                    throw new RuntimeException('Gemini no devolvió un array documentos.');
                }
                foreach ($resultado->documentos as $documento) {
                    if (! is_object($documento)) {
                        throw new RuntimeException('Gemini devolvió un documento inválido.');
                    }
                }

                $documentos = json_decode($texto, true, 512, JSON_THROW_ON_ERROR)['documentos'];
                // A veces el modelo devuelve solo {"tipo": ...} sin los datos: eso no es una lectura, se reintenta
                $tiposValidos = $esquema['properties']['documentos']['items']['properties']['tipo']['enum'];
                foreach ($documentos as $doc) {
                    $tipoDoc = $doc['tipo'] ?? 'otro';
                    if (in_array($tipoDoc, $tiposValidos, true) && ! in_array($tipoDoc, ['otro', 'cierre_barra'], true) && empty($doc[$tipoDoc])) {
                        throw new RuntimeException("Gemini devolvió el documento '{$tipoDoc}' sin datos.");
                    }
                }
                $documentos = $this->normalizar($documentos, $esquema['properties']['documentos']);
                $tipos = $esquema['properties']['documentos']['items']['properties']['tipo']['enum'];
                foreach ($documentos as &$documento) {
                    $documento['tipo'] = in_array($documento['tipo'] ?? null, $tipos, true) ? $documento['tipo'] : 'otro';
                }
                unset($documento);

                return ['ia_disponible' => true, 'modelo' => $modelo, 'error' => null, 'documentos' => $documentos];
            } catch (Throwable $excepcion) {
                // Los errores de transporte pueden contener la URL con la clave: nunca registrar su mensaje.
                $error = $excepcion instanceof RuntimeException && str_starts_with($excepcion->getMessage(), 'Gemini ')
                    ? $excepcion->getMessage()
                    : 'Error de transporte o JSON inválido en la respuesta de Gemini.';
                Log::warning('Extracción de cierre fallida.', ['modelo' => str_replace($this->apiKey, '[oculta]', $modelo), 'error' => $error]);
            }
        }

        return $this->fallo($error);
    }

    private function leerDocumento(string $entrada): string
    {
        if (str_starts_with($entrada, 'data:')) {
            $partes = explode(',', $entrada, 2);
            if (count($partes) !== 2 || ! str_contains($partes[0], ';base64') || base64_decode($partes[1], true) === false) {
                throw new RuntimeException('Documento base64 inválido.');
            }

            return $partes[1];
        }

        $rutas = [];
        if (str_starts_with($entrada, '/')) {
            $rutas[] = $entrada;
        }
        $rutas[] = public_path($entrada);
        $rutas[] = storage_path('app/public/'.ltrim(str_replace('storage/', '', $entrada), '/'));
        foreach ($rutas as $ruta) {
            if (is_file($ruta) && is_readable($ruta)) {
                $contenido = file_get_contents($ruta);
                if ($contenido !== false) {
                    return base64_encode($contenido);
                }
            }
        }

        throw new RuntimeException('Documento no encontrado.');
    }

    private function normalizar(mixed $valor, array $esquema): mixed
    {
        if ($valor === null) {
            return null;
        }
        if (in_array($esquema['type'], ['NUMBER', 'INTEGER'], true) && is_string($valor)) {
            $numero = preg_replace('/(?:Bs\.?|\s|\x{00a0})/iu', '', $valor);
            if ($numero === '' || preg_match('/^[—–-]$/u', $numero)) {
                return 0;
            }
            if (str_contains($numero, ',')) {
                $numero = str_replace(',', '.', str_replace('.', '', $numero));
            } elseif (preg_match('/^[+-]?\d{1,3}(?:\.\d{3})+$/', $numero)) {
                $numero = str_replace('.', '', $numero);
            }

            return is_numeric($numero) && is_finite((float) $numero) ? $numero + 0 : null;
        }
        if (is_array($valor) && $esquema['type'] === 'ARRAY') {
            return array_map(fn ($item) => $this->normalizar($item, $esquema['items']), $valor);
        }
        if (is_array($valor) && $esquema['type'] === 'OBJECT') {
            foreach ($valor as $campo => $dato) {
                if (isset($esquema['properties'][$campo])) {
                    $valor[$campo] = $this->normalizar($dato, $esquema['properties'][$campo]);
                }
            }
        }

        return $valor;
    }

    private function fallo(string $error): array
    {
        Log::warning('Extracción de cierre no disponible.', ['error' => $error]);

        return ['ia_disponible' => false, 'modelo' => null, 'error' => $error, 'documentos' => []];
    }
}
