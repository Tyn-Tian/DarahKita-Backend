<?php

use App\Http\Controllers\BloodStockController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorController;
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
    Route::get('/profile', [DonorController::class, 'getProfile']);
    Route::patch('/profile', [DonorController::class, 'updateProfile']);

    Route::get('/blood-stocks', [BloodStockController::class, 'getBloodStocks']);

    Route::get('/donations-month', [DonationController::class, 'getDonationsByMonth']);
});

Route::get('/top-donors', [DonorController::class, 'getTopDonors']);
