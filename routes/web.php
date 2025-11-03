<?php

use App\Http\Controllers\AuthController;
use Barryvdh\DomPDF\Facade\Pdf;
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


Route::controller(AuthController::class)->group(function () {
    Route::get('login', 'index')->name('index');
    Route::post('login', 'login')->name('login');
    Route::get('logout', 'logout')->name('logout');
});

Route::get('/pdf/{id}', function ($id) {
    $referral = App\Models\Referral::findOrFail($id);
    $pdfBase64 = $referral->encoded_base;

    if (!$pdfBase64) {
        return 'No PDF found';
    }

    $pdfContent = base64_decode($pdfBase64);

    return response($pdfContent)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="referral.pdf"');
});

// Public Referral Viewing Routes (No Authentication Required)
use App\Http\Controllers\ReferralController;

Route::get('/view/{id}', [ReferralController::class, 'showNricForm'])->name('referral.nric.form');
Route::post('/view/{id}/verify', [ReferralController::class, 'verifyAndShowPdf'])->name('referral.verify.nric');