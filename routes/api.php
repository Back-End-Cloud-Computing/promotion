<?php

use App\Http\Controllers\Api\DescontoController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../app/Domain/Campaigns/routes/api.php';
require __DIR__.'/../app/Domain/Promotions/routes/api.php';
require __DIR__.'/../app/Domain/Coupons/routes/api.php';

Route::get('cupons/{codigo}/validar', [DescontoController::class, 'validar']);
