<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

require app_path('Modules/Site/routes/api.php');
require app_path('Modules/TelegramAccess/routes/api.php');
require app_path('Modules/TelegramSupport/routes/api.php');
require app_path('Modules/Monitoring/routes/api.php');
