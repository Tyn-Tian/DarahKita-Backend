<?php

use App\Http\Controllers\BloodStockController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\DonorScheduleController;
use App\Http\Controllers\PmiCenterController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/google-login', [UserController::class, 'googleLogin']);
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware('jwt.verify')->group(function () {
    Route::middleware('role:donor')->group(function () {
        Route::get('/profile', [DonorController::class, 'getProfile']);
        Route::patch('/profile', [DonorController::class, 'updateProfile']);

        Route::post('/donor-schedules/{id}', [DonorScheduleController::class, 'postRegisterDonorSchedule']);
    });

    Route::middleware('role:pmi')->group(function () {
        Route::get('/pmi-profile', [PmiCenterController::class, 'getPmiProfile']);
        Route::patch('/pmi-profile', [PmiCenterController::class, 'updatePmiProfile']);

        Route::post('/donor-schedules', [DonorScheduleController::class, 'postCreateDonorSchedule']);
        Route::patch('/donor-schedules/{id}', [DonorScheduleController::class, 'patchUpdateDonorSchedule']);
        Route::get('/donor-schedules/{id}/participants', [DonorScheduleController::class, 'getDonorScheduleParticipants']);
        Route::post('/donor-schedules/{id}/participants', [DonorScheduleController::class, 'postAddParticipant']);
        Route::get('/donor-schedules/{id}/participants/{donorId}', [DonorScheduleController::class, 'getDonorScheduleParticipantDetail']);
        Route::post('/donor-schedules/{id}/participants/{donorId}', [DonorScheduleController::class, 'postUpdateStatusParticipant']);

        Route::post('/donors', [DonationController::class, 'postAddDonor']);
    });

    Route::get('/top-donors', [DonorController::class, 'getTopDonors']);

    Route::get('/blood-stocks', [BloodStockController::class, 'getBloodStocks']);

    Route::get('/donations-month', [DonationController::class, 'getDonationsByMonth']);

    Route::get('/histories', [DonationController::class, 'getHistories']);
    Route::get('/histories/{id}', [DonationController::class, 'getHistoryDetail']);

    Route::get('/donor-schedules', [DonorScheduleController::class, 'getDonorSchedules']);
    Route::get('/donor-schedules/{id}', [DonorScheduleController::class, 'getDonorScheduleDetail']);
});
