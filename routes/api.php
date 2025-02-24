<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// auth
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('register', 'register')->name('register');
    Route::post('2fa/verify', 'verify2FA')->name('verify2FA');
    
    //Route::post('login', 'postLogin')->name('login.post');
    Route::get('logout', 'logout')->name('logout')->middleware('auth:sanctum');
    Route::get('2fa/setup', 'setup2FA')->name('setup2FA')->middleware('auth:sanctum');
    Route::post('2fa/verify-setup', 'verifySetup2FA')->name('verifySetup2FA')->middleware('auth:sanctum');
    Route::get('2fa/deactivate', 'deactivate2FA')->name('deactivate2FA')->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('rents', RentController::class);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');