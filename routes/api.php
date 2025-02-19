<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// auth
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('register', 'register')->name('register');
    //Route::post('login', 'postLogin')->name('login.post');
    Route::get('logout', 'logout')->name('logout')->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('rents', RentController::class);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');