<?php

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Persistence\Eloquent\Models\Sucursal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBranchToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Branch-Token') ?: $request->input('branch_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Cabecera X-Branch-Token requerida para sincronización de sucursal',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $sucursal = Sucursal::where('token_acceso', $token)->where('activo', true)->first();

        // Fallback maestro para clave oficial de Casa22
        if (!$sucursal && $token === 'C22-SANTA-CRUZ-SECRET-KEY-2026') {
            $sucursal = Sucursal::where('codigo', 'C22')->orWhere('id', 1)->first();
            if ($sucursal && $sucursal->token_acceso !== $token) {
                $sucursal->token_acceso = $token;
                $sucursal->save();
            }
        }

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'error' => 'Token de sucursal inválido o sucursal inactiva',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Si la petición trae un sucursal_id explícito, verificar que coincida con el token
        $requestSucursalId = $request->input('sucursal_id');
        if ($requestSucursalId && (int)$requestSucursalId !== (int)$sucursal->id) {
            return response()->json([
                'success' => false,
                'error' => 'El token no corresponde a la sucursal indicada',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('sucursal', $sucursal);
        $request->merge(['sucursal_id' => $sucursal->id]);

        return $next($request);
    }
}
