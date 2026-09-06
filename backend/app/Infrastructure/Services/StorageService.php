<?php

namespace App\Infrastructure\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    private string $disco;

    public function __construct(string $disco = 'public')
    {
        $this->disco = config('filesystems.default', $disco);
    }

    /**
     * Almacena una fotografía de evidencia (ingreso o baja) en el almacenamiento configurado.
     */
    public function guardarFoto(UploadedFile $archivo, string $carpeta = 'evidencias'): string
    {
        $nombreArchivo = (string) Str::uuid() . '.' . $archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs($carpeta, $nombreArchivo, $this->disco);
        return $ruta;
    }

    /**
     * Almacena una imagen codificada en base64 (sincronizada desde la app Flutter).
     */
    public function guardarFotoBase64(string $base64Data, string $carpeta = 'evidencias', string $extension = 'jpg'): string
    {
        // Limpiar encabezado data:image/...;base64, si existe
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $extension = strtolower($type[1]);
        }

        $decodedData = base64_decode($base64Data);
        $nombreArchivo = (string) Str::uuid() . '.' . $extension;
        $ruta = "{$carpeta}/{$nombreArchivo}";

        Storage::disk($this->disco)->put($ruta, $decodedData);
        return $ruta;
    }

    public function eliminarFoto(string $ruta): bool
    {
        if (Storage::disk($this->disco)->exists($ruta)) {
            return Storage::disk($this->disco)->delete($ruta);
        }
        return false;
    }

    public function obtenerUrl(string $ruta): string
    {
        return Storage::disk($this->disco)->url($ruta);
    }
}
