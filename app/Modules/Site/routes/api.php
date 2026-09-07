<?php

use App\Modules\Site\Http\Controllers\Api\SitePingController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/sites/{site}/ping', SitePingController::class);
});
