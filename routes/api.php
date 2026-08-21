<?php

use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\DescontoController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../app/Domain/Campaigns/routes/api.php';
require __DIR__.'/../app/Domain/Promotions/routes/api.php';

Route::get('cupons/{codigo}/validar', [DescontoController::class, 'validar']);

/*
|--------------------------------------------------------------------------
| Administração
|--------------------------------------------------------------------------
*/

// O pluralizador do Laravel é inglês e derivaria {cupon} do nome em português;
// o parâmetro é nomeado à mão.
Route::middleware(['jwt', 'admin'])->group(function () {
    Route::apiResource('cupons', CupomController::class)
        ->parameters(['cupons' => 'cupom']);
});
