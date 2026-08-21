<?php

use App\Domain\Promotions\Controllers\PromotionController;
use App\Domain\Promotions\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Público
|--------------------------------------------------------------------------
*/

// Mantém o contrato do projeto de referência da equipe.
Route::get('sale', [SaleController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Administração
|--------------------------------------------------------------------------
*/

Route::middleware(['jwt', 'admin'])->group(function () {
    Route::apiResource('promotions', PromotionController::class);
});
