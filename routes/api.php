<?php

use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\DescontoController;
use App\Http\Controllers\Api\PromocaoController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../app/Domain/Campaigns/routes/api.php';

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

// Mantém o contrato do projeto de referência da equipe.
Route::get('sale', [SaleController::class, 'index']);

Route::get('cupons/{codigo}/validar', [DescontoController::class, 'validar']);

/*
|--------------------------------------------------------------------------
| Administração
|--------------------------------------------------------------------------
*/

// O pluralizador do Laravel é inglês e derivaria {promoco} e {cupon} dos nomes
// em português; os parâmetros são nomeados à mão.
Route::middleware(['jwt', 'admin'])->group(function () {
    Route::apiResource('promocoes', PromocaoController::class)
        ->parameters(['promocoes' => 'promocao']);

    Route::apiResource('cupons', CupomController::class)
        ->parameters(['cupons' => 'cupom']);
});
