<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege as rotas /internal, consumidas por Carrinho e Pedido. Mesmo esquema do
 * projeto de referência: segredo compartilhado, fora do API Gateway.
 */
class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('service.internal_secret');

        if (empty($expected)) {
            return response()->error(500, 'Serviço sem INTERNAL_SECRET configurado');
        }

        $received = $request->header('x-internal-secret');

        // Comparação em tempo constante: comparar segredo com === vaza informação
        // pelo tempo de resposta.
        if (! is_string($received) || ! hash_equals($expected, $received)) {
            return response()->error(403, 'Segredo interno inválido');
        }

        return $next($request);
    }
}
