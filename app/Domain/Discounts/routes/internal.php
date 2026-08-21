<?php

use App\Domain\Discounts\Controllers\DiscountController;
use Illuminate\Support\Facades\Route;

Route::middleware('interno')->prefix('internal')->group(function () {
    Route::post('discounts/calculate', [DiscountController::class, 'calculate']);
    Route::post('coupons/{code}/consume', [DiscountController::class, 'consume']);
});
