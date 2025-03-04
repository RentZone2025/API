<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;

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
    Route::get('rents/archives', [RentController::class, 'getArchives'])->name('getArchives');
    Route::resource('rents', RentController::class);
    Route::controller(UserController::class)->group(function () {
        Route::put('users/{id}', 'update')->name('update');
        Route::post('users/change-password', 'changePassword')->name('changePassword');
        Route::post('users/change-billing', 'changeBilling')->name('changeBilling');
        Route::post('users/change-shipping', 'changeShipping')->name('changeShipping');
    });

    Route::get('/subscriptions/plans', [SubscriptionController::class, 'getSubscriptionPlans']);
    Route::post('/subscriptions/create-checkout-session', [SubscriptionController::class, 'createCheckoutSession']);
    Route::post('/subscriptions/', [SubscriptionController::class, 'saveSubscription']);

});

/*
Route::get('/user', function (Request $request) {

    $user = $request->user()->loadMissing(['shipping', 'billing']);

    $subscription = $user->subscription();

    if (!$subscription->items || count($subscription->items) === 0) {
        return response()->json(['error' => 'No items found in subscription'], 404);
    }    

    $product_id = optional($subscription->items->first())->stripe_product;

    if (!$product_id) {
        return response()->json(['error' => 'No product found in subscription'], 404);
    }

    try {
        $product = Product::retrieve($product_id);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Product not found'], 404);
    }
    
    return [
        'user' => $user,
        'product' => $product,
        'shipping' => $user->shipping ? $user->shipping->makeHidden(['created_at', 'updated_at']) :  null, 
        'billing' => $user->billing ? $user->billing->makeHidden(['created_at', 'updated_at']) : null,
    ];
    
})->middleware('auth:sanctum');*/

Route::get('/user', function (Request $request) {

    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

    $user = $request->user()->loadMissing(['shipping', 'billing']);

    // Ellenőrizzük, hogy van-e aktív előfizetés
    $subscription = $user->subscription();

    if ($subscription) {

        // Ellenőrizzük, hogy vannak-e tételek az előfizetésben
        if (!$subscription->items || count($subscription->items) === 0) {
            return response()->json(['error' => 'No items found in subscription'], 404);
        }

        // Biztonságosan lekérjük az első tétel stripe_product azonosítóját
        $product_id = optional($subscription->items->first())->stripe_product;

        if ($product_id) {

            try {
                $product = Product::retrieve($product_id);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Product not found: ' . $e->getMessage()], 404);
            }

            if ($product->description) {
                $product->benefits = explode(" - ", $product->description);
            }

            // price lekérése
            $price_id = optional($subscription->items->first())->stripe_price;

            if (!$price_id) {
                return response()->json(['error' => 'No price found in subscription'], 404);
            }

            try {
                $price = Price::retrieve($price_id);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Price not found: ' . $e->getMessage()], 404);
            }

            $data = [
                "subscription" => $subscription,
                "product" => $product,
                "price" => $price,
            ];

        } else {
            $data = null;
        }

    } else {
        $data = null;
    }

    return response()->json([
        'user' => $user,
        'subscription' => $data,
        'shipping' => $user->shipping ? $user->shipping->makeHidden(['created_at', 'updated_at']) : null,
        'billing' => $user->billing ? $user->billing->makeHidden(['created_at', 'updated_at']) : null,
    ]);
})->middleware('auth:sanctum');