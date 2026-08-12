<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege as rotas /internal, consumidas por Carrinho e Pedido. Mesmo esquema do
 * projeto de referência: segredo compartilhado, fora do API Gateway.
 */
class VerificaSegredoInterno
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = config('servico.internal_secret');

        if (empty($esperado)) {
            return response()->json(['error' => 'Serviço sem INTERNAL_SECRET configurado'], 500);
        }

        $recebido = $request->header('x-internal-secret');

        // Comparação em tempo constante: comparar segredo com === vaza informação
        // pelo tempo de resposta.
        if (! is_string($recebido) || ! hash_equals($esperado, $recebido)) {
            return response()->json(['error' => 'Segredo interno inválido'], 403);
        }

        return $next($request);
    }
}
