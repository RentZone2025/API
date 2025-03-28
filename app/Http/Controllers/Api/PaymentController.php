<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Product;

class PaymentController extends Controller
{

    /*public function createCheckoutSession(Request $request){

        $frontend_url = env('FRONTEND_URL', 'http://localhost:4200');

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $lineItems = [];
        foreach ($request->products as $product) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'huf',
                    'product_data' => [
                        'name' => $product['name'],
                        "description" => "Ez egy leirás"
                    ],
                    'unit_amount' => $product['price'] * 100, // Centben megadva
                    "discount" => 2000
                ],
                'quantity' => $product['quantity'],
            ];
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $frontend_url . '/user/rent-confirm?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend_url . '/user/rent-cancel?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return response()->json(['url' => $session->url]);

    }*/

    public function createCheckoutSession(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $frontend_url = env('FRONTEND_URL', 'http://localhost:4200');

        $user = auth()->user();
        $lineItems = [];

        foreach ($request->products as $product) {
            $price = $product['price'];
            $originalPrice = null;

            // 🔹 1. Az akciós termék hozzáadása a megfelelő árral
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'huf',
                    'product_data' => [
                        'name' => $product['name'],
                        
                    ],
                    'unit_amount' => $price * 100,
                ],
                'quantity' => $product['quantity'],
            ];
        }
        $subscription = $request->user()->subscription();
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
                    $subscription->product = $product;
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Product not found: ' . $e->getMessage()], 404);
                }
            }
        }
        //return $subscription;

        // 🔹 Ha a user előfizető, akkor kupon alkalmazása
        $discounts = [];
        if ($user->subscription() != null && $user->subscription()->product->metadata->type == "gold") { 
            $discounts[] = [
                'coupon' => 'cC0V0xEg', // ⚠️ Itt a Stripe-ban lévő kupon kódját kell megadni
            ];
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            "customer_email" => "komaromijano2002@gmail.com",
            'mode' => 'payment',
            'discounts' => $discounts, // 🎯 Itt alkalmazzuk a kuponkedvezményt!
            'success_url' => $frontend_url . '/user/rent-confirm?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend_url . '/user/rent-cancel?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function saveOrder(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $session = Session::retrieve($request->session_id);

        // request order items
        $orderItems = $request->orderItems;

        return response()->json(['success' => true]);

    }


}
