<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('integration.api_token');

        if (! filled($token)) {
            return response()->json([
                'message' => 'API de integração não configurada. Defina o token em Configurações → Integração n8n.',
            ], 503);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Api-Key')
            ?? $request->query('api_token');

        if (! hash_equals($token, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
