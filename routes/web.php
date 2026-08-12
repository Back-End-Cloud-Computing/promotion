<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'GANJJ Promotion Service',
        'status' => 'ok',
    ]);
});
