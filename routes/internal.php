<?php

use App\Http\Controllers\Api\DescontoController;
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

Route::middleware('interno')->prefix('internal')->group(function () {
    Route::post('descontos/calcular', [DescontoController::class, 'calcular']);
    Route::post('cupons/{codigo}/consumir', [DescontoController::class, 'consumir']);
});

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
