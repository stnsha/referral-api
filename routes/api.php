<?php

use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\ExternalOrganizationController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormDetailsController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReferralAttachmentController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ExternalRefereeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('token.auth')->group(function () {
    Route::resource('business-units', BusinessUnitController::class);

    Route::resource('form', FormController::class)->only(['store', 'update', 'destroy']);

    Route::prefix('form')->controller(FormController::class)->group(function () {
        Route::get('show/{business_unit_id}', 'show');
    });

    Route::resource('formDetails', FormDetailsController::class)->only(['create', 'update', 'show', 'destroy']);

    Route::resource('referral', ReferralController::class)->only(['index', 'store', 'show']);

    Route::prefix('referral')->controller(ReferralController::class)->group(function () {
        Route::put('', 'update')->name('referral.update');
    });

    Route::prefix('attachment')->controller(ReferralAttachmentController::class)->group(function () {
        Route::get('{attachment}', 'download')->name('attachment.download');
    });

    Route::prefix('reference')->controller(ReferenceController::class)->group(function () {
        Route::get('status', 'referralStatus')->name('reference.status');
    });

    Route::apiResource('external-referees', ExternalRefereeController::class);
    Route::apiResource('external-organizations', ExternalOrganizationController::class);
});
