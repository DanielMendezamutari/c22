<?php

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Webhook-Secret') ?: $request->input('webhook_secret');
        $expectedSecret = env('WHATSAPP_WEBHOOK_SECRET', 'puntofrio_wh_secret_2026_super');

        if (!$secret || !hash_equals((string) $expectedSecret, (string) $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'Cabecera X-Webhook-Secret requerida o inválida para el microservicio de WhatsApp',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
