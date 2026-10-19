<?php

use App\Support\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Serviço a serviço
|--------------------------------------------------------------------------
|
| Fora do prefixo /api porque estas rotas não passam pelo API Gateway — são
| chamadas diretas entre serviços dentro do cluster.
|
*/

require __DIR__.'/../app/Domain/Discounts/routes/internal.php';

/*
|--------------------------------------------------------------------------
| Health checks
|--------------------------------------------------------------------------
|
| Alvos das probes do Kubernetes. Sem autenticação: o kubelet não manda header.
|
*/

// Liveness: o processo está vivo. Não toca no banco de propósito — uma liveness
// que depende do banco faz o Kubernetes matar todos os pods quando o MySQL
// oscila, transformando lentidão de banco em apagão total.
Route::get('health', fn () => response()->json(['status' => 'ok']));

// Readiness: o pod consegue atender. Aqui sim o banco importa, porque sem ele
// nenhuma requisição real é respondida.
Route::get('health/ready', function () {
    try {
        DB::connection()->getPdo();
    } catch (Throwable) {
        return response()->json(['status' => 'sem conexão com o banco'], 503);
    }

    return response()->json(['status' => 'ready']);
});

/*
|--------------------------------------------------------------------------
| Métricas
|--------------------------------------------------------------------------
|
| Raspadas pelo Prometheus direto no pod (anotações do Deployment). O Ingress
| bloqueia /metrics como bloqueia /internal: é dado operacional, não API pública.
*/
Route::get('metrics', fn () => response(Metrics::render(), 200, [
    'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
]));
