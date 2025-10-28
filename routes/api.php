<?php

use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\ExternalOrganizationController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormDetailsController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReferralAttachmentController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ExternalRefereeController;
use App\Http\Controllers\ExternalReferralController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ODBController;
use App\Http\Controllers\ReportController;
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

Route::prefix('auth')->controller(ODBController::class)->group(function () {
    Route::post('', 'auth')->name('auth.login');
    Route::post('verify', 'verifyToken')->name('auth.verify');
});

Route::middleware('token.auth')->group(function () {
    Route::resource('business-units', BusinessUnitController::class);

    Route::resource('form', FormController::class)->only(['store', 'update']);

    Route::prefix('form')->controller(FormController::class)->group(function () {
        Route::put('hide/{form}', 'hide')->name('form.hide');
        Route::put('unhide/{form}', 'unhide')->name('form.unhide');
    });

    Route::prefix('form')->controller(FormController::class)->group(function () {
        Route::post('list', 'index')->name('form.index');
    });

    Route::prefix('form')->controller(FormController::class)->group(function () {
        Route::get('show/{business_unit_id}', 'show');
        Route::get('all', 'allForms');
    });

    Route::resource('formDetails', FormDetailsController::class)->only(['create', 'update', 'show', 'destroy']);

    Route::prefix('referral')->controller(ReferralController::class)->group(function () {
        Route::put('', 'update')->name('referral.update');
        Route::get('download/{id}', 'download')->name('referral.download');
        Route::get('notification', 'notification')->name('referral.notification');
        Route::post('search', 'search')->name('referral.search');
    });

    Route::resource('referral', ReferralController::class)->only(['index', 'store', 'show']);

    Route::prefix('attachment')->controller(ReferralAttachmentController::class)->group(function () {
        Route::get('{attachment}', 'download')->name('attachment.download');
    });

    Route::apiResource('external-referees', ExternalRefereeController::class);
    Route::apiResource('external-organizations', ExternalOrganizationController::class);
    Route::apiResource('external-referral', ExternalReferralController::class);

    Route::prefix('report')->controller(ReportController::class)->group(function () {
        Route::post('/', 'index')->name('report.index');
        Route::get('chart', 'chart')->name('report.chart');
        Route::get('dashboard', 'dashboard')->name('report.dashboard');
        Route::get('summary', 'summary')->name('report.summary');
    });

    Route::prefix('library')->controller(LibraryController::class)->group(function () {
        Route::get('status', 'displayStatus')->name('library.status');
        Route::get('priority', 'displayPriority')->name('library.priority');
    });
});