<?php

use App\Http\Controllers\Api\Admin\AdminStripeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// auth
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('register', 'register')->name('register');
    Route::post('forgot-password', 'forgotPassword')->name('forgotPassword');
    Route::post('reset-password', 'resetPassword')->name('resetPassword');
    Route::post('2fa/verify', 'verify2FA')->name('verify2FA');
    Route::post('/email/verify/{id}/{hash}', "verifyEmail")->name('verifyEmail');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('logout', 'logout')->name('logout');
        Route::get('2fa/setup', 'setup2FA')->name('setup2FA');
        Route::post('2fa/verify-setup', 'verifySetup2FA')->name('verifySetup2FA');
        Route::get('2fa/deactivate', 'deactivate2FA')->name('deactivate2FA');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('rents/archives', [RentController::class, 'getArchives'])->name('getArchives');
    Route::resource('rents', RentController::class);
    Route::controller(UserController::class)->group(function () {
        Route::get('user', 'index')->name('index');
        Route::put('users/{id}', 'update')->name('update');
        Route::post('users/change-password', 'changePassword')->name('changePassword');
        Route::post('users/change-billing', 'changeBilling')->name('changeBilling');
        Route::post('users/change-shipping', 'changeShipping')->name('changeShipping');
    });

    Route::get('/subscriptions/plans', [SubscriptionController::class, 'getSubscriptionPlans']);
    Route::post('/subscriptions/create-checkout-session', [SubscriptionController::class, 'createCheckoutSession']);
    Route::post('/subscriptions/', [SubscriptionController::class, 'saveSubscription']);
    Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancelSubscription']);

    Route::post('/payments/create-checkout-session', [PaymentController::class, 'createCheckoutSession']);

    // ######
    // ADMIN
    // ######
    Route::prefix('admin')->group(function () {
        Route::controller(AdminStripeController::class)->group(function () {
            Route::get('/subscriptions/plans', 'getAllPlans')->name('getAllPlans');
            Route::post('/subscriptions/plans', 'createPlan')->name('createPlan');
            Route::get('/subscriptions/plans/{id}', 'getPlan')->name('getPlan');
            Route::get('/subscriptions/prices/{id}', 'getPrice')->name('getPrice');

            // INVOICE
            Route::get('/subscriptions/invoices', 'getAllInvoices')->name('getAllInvoices');
            Route::get('/subscriptions/invoices/{id}', 'getInvoice')->name('getInvoice');

            // SUBSCRIPTION
            Route::get('/subscriptions/subscriptions', 'getAllSubscriptions')->name('getAllSubscriptions');
            Route::get('/subscriptions/subscriptions/{id}', 'getSubscription')->name('getSubscription');
        });
    });
});