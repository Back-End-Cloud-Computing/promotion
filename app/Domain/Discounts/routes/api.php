<?php

use App\Domain\Discounts\Controllers\DiscountController;
use Illuminate\Support\Facades\Route;

Route::get('coupons/{code}/validate', [DiscountController::class, 'validate']);
