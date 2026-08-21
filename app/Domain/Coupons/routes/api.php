<?php

use App\Domain\Coupons\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt', 'admin'])->group(function () {
    Route::apiResource('coupons', CouponController::class);
});
