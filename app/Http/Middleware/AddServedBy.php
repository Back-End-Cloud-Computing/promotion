<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica o pod que atendeu (no Kubernetes, o hostname é o nome do pod).
 * É a evidência de balanceamento entre réplicas pedida na N2.
 */
class AddServedBy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Served-By', gethostname() ?: 'unknown');

        return $response;
    }
}
