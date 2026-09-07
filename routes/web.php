<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return auth()->check() ? redirect()->route('portal.dashboard') : view('welcome');
});

Auth::routes(['verify' => true]);

Route::middleware('auth')->get('/portal/pending-approval', function () {
    return view('portal.pending-approval');
})->name('portal.pending-approval');

Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
});

require app_path('Modules/Shared/routes/web.php');
require app_path('Modules/Shared/routes/trash.php');
require app_path('Modules/Shared/routes/companies.php');
require app_path('Modules/Shared/routes/statuses.php');
require app_path('Modules/Site/routes/web.php');
require app_path('Modules/Ftp/routes/web.php');
require app_path('Modules/Hosting/routes/web.php');
require app_path('Modules/UserManagement/routes/web.php');
require app_path('Modules/Audit/routes/web.php');
require app_path('Modules/TelegramSupport/routes/web.php');
