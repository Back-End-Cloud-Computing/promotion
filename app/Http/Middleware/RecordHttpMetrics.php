<?php

namespace App\Http\Middleware;

use App\Support\Metrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mede cada requisição para o /metrics. O label de rota é o template do
 * Laravel (`api/coupons/{code}/validate`), nunca a URL real: id ou código de
 * cupom no label criaria uma série nova por valor.
 */
class RecordHttpMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);
        $response = $next($request);

        $uri = $request->route()?->uri();

        // Raspar o /metrics não conta como tráfego do serviço.
        if ($uri !== 'metrics') {
            Metrics::httpRequest(
                $request->getMethod(),
                $uri ?? Metrics::UNKNOWN_ROUTE,
                $response->getStatusCode(),
                (hrtime(true) - $start) / 1e9,
            );
        }

        return $response;
    }
}
