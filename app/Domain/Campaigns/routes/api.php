<?php

use App\Domain\Campaigns\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt', 'admin'])->group(function () {
    Route::apiResource('campaigns', CampaignController::class);
});
