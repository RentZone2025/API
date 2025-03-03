<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// auth
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('register', 'register')->name('register');
    Route::post('forgot-password', 'forgotPassword')->name('forgotPassword');
    Route::post('reset-password', 'resetPassword')->name('resetPassword');
    Route::post('2fa/verify', 'verify2FA')->name('verify2FA');

    Route::post('/email/verify/{id}/{hash}', "verifyEmail")->name('verifyEmail');
    
    //Route::post('login', 'postLogin')->name('login.post');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('logout', 'logout')->name('logout');
        Route::get('2fa/setup', 'setup2FA')->name('setup2FA');
        Route::post('2fa/verify-setup', 'verifySetup2FA')->name('verifySetup2FA');
        Route::get('2fa/deactivate', 'deactivate2FA')->name('deactivate2FA');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rents/archives', [RentController::class,'getArchives'])->name('getArchives');
    Route::resource('rents', RentController::class);
    Route::controller(UserController::class)->group(function () {
        Route::put('users/{id}', 'update')->name('update');
        Route::post('users/change-password', 'changePassword')->name('changePassword');
        Route::post('users/change-billing', 'changeBilling')->name('changeBilling');
        Route::post('users/change-shipping', 'changeShipping')->name('changeShipping');
    });
    
});

Route::get('/user', function (Request $request) {

    $user = $request->user()->loadMissing(['shipping', 'billing']);
    
    return [
        'user' => $user,
        'shipping' => $user->shipping ? $user->shipping->makeHidden(['created_at', 'updated_at']) :  null, 
        'billing' => $user->billing ? $user->billing->makeHidden(['created_at', 'updated_at']) : null,
    ];
})->middleware('auth:sanctum');
