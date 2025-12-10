<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExternalReferralController;
use App\Http\Controllers\ReferralController;
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
    return redirect()->route('login');
});


Route::get('/export/{referral}/internal', [ReferralController::class, 'exportReferral'])
    ->name('referrals.internal');

Route::get('/export/{referral}/external', [ExternalReferralController::class, 'exportReferral'])
    ->name('referrals.external');

Route::controller(AuthController::class)->group(function () {
    Route::get('login', 'index')->name('index');
    Route::post('login', 'login')->name('login');
    Route::get('logout', 'logout')->name('logout');
});


Route::get('/view/{id}', [ReferralController::class, 'showNricForm'])->name('referral.nric.form');
Route::post('/view/{id}/verify', [ReferralController::class, 'verifyAndShowPdf'])->name('referral.verify.nric');