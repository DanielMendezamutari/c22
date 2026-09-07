<?php

namespace App\Infrastructure\Http\Controllers\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AlertaDiscrepancia;
use App\Infrastructure\Persistence\Eloquent\Models\AdminDispositivo;
use App\Infrastructure\Persistence\Eloquent\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AlertaController extends Controller
{
    public function listarDiscrepancias(Request $request): JsonResponse
    {
        try {
            $query = AlertaDiscrepancia::with([
                'sucursal',
                'turnoSaliente.usuario',
                'turnoEntrante.usuario',
                'producto',
            ])->orderBy('id', 'desc');

            if ($request->boolean('solo_pendientes', true)) {
                $query->where('resuelto', false);
            }

            $alertas = $query->limit(50)->get();

            return response()->json([
                'success' => true,
                'total_pendientes' => AlertaDiscrepancia::where('resuelto', false)->count(),
                'data' => $alertas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function resolverDiscrepancia(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $alerta = AlertaDiscrepancia::findOrFail($id);
            $alerta->resuelto = true;
            if ($request->filled('observaciones')) {
                $alerta->observaciones = $request->input('observaciones');
            }
            $alerta->save();

            return response()->json([
                'success' => true,
                'message' => 'Alerta de discrepancia marcada como resuelta.',
                'data' => $alerta,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function resolverAdmin(Request $request): ?Usuario
    {
        $user = auth('sanctum')->user() ?? $request->user();
        if (!$user && $request->filled('usuario_id')) {
            $user = Usuario::find((int) $request->usuario_id);
        }
        if (!$user) {
            $user = Usuario::where('rol', 'admin')->first();
        }
        return ($user && $user->rol === 'admin') ? $user : null;
    }

    public function registrarDispositivoMaestro(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:150',
            'nombre_dispositivo' => 'nullable|string|max:100',
            'fcm_token' => 'nullable|string|max:500',
            'usuario_id' => 'nullable|integer',
        ]);

        try {
            $user = $this->resolverAdmin($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Solo administradores pueden registrar dispositivos de alerta.',
                ], 403);
            }

            $dispositivo = AdminDispositivo::updateOrCreate(
                [
                    'usuario_id' => $user->id,
                    'device_id' => $request->input('device_id'),
                ],
                [
                    'nombre_dispositivo' => $request->input('nombre_dispositivo', 'Celular Personal Admin'),
                    'fcm_token' => $request->input('fcm_token', ''),
                    'activo' => true,
                    'ultimo_acceso' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo registrado como celular personal para alertas.',
                'data' => $dispositivo,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function desvincularDispositivo(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'nullable|string|max:150',
        ]);

        try {
            $deviceId = $request->input('device_id');
            if ($deviceId) {
                AdminDispositivo::where('device_id', $deviceId)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo desvinculado de alertas exitosamente.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarDispositivosMaestros(Request $request): JsonResponse
    {
        try {
            $user = $this->resolverAdmin($request);
            $userId = $user ? $user->id : 1;
            $dispositivos = AdminDispositivo::where('usuario_id', $userId)
                ->orderBy('ultimo_acceso', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $dispositivos,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
