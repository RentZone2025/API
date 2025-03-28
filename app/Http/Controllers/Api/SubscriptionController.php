<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;
use Stripe\Checkout\Session;
use Laravel\Cashier\Subscription;
use Stripe\Invoice;
use Stripe\Refund;
use App\Models\User;
use Carbon\Carbon;

class SubscriptionController extends Controller
{

    public function getSubscriptionPlans()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Előfizetési terveket lekérdezzük
        $prices = Price::all(['limit' => 100]);

        $subscriptions = [];
        foreach ($prices->data as $price) {


            $product = Product::retrieve($price->product); // Kapcsolódó termék lekérése

            $id = $product->metadata['id'] ?? null;

            $days = 0;

            // Ellenőrizzük, hogy az interval_count van-e, ha igen, akkor három hónapra is megjeleníthetjük
            $interval = $price->recurring ? $price->recurring->interval : 'one-time';
            $intervalCount = $price->recurring ? $price->recurring->interval_count : 1;

            if ($intervalCount) {
                $add = $interval == 'month' ? 30 : ($interval == 'year' ? 365 : 0);
                $days = $intervalCount * $add;
            }

            // Ha az intervallum 'month', és az interval_count 3, akkor háromhavonta ismétlődik
            if ($interval == 'month' && $intervalCount == 3) {
                $interval = 'quarterly'; // 3 hónapos intervallum
            }

            // Ha a termék neve még nem létezik a csoportosított tömbben, akkor inicializáljuk
            if (!isset($subscriptions[$id])) {
                $subscriptions[$id] = [];
            }

            if ($product->description) {
                $product->benefits = explode(" - ", $product->description);
            }

            // Termék hozzáadása a megfelelő csoporthoz
            $subscriptions[$id]['subplans'][] = [
                'id' => $price->id,
                'name' => $product->name,
                'metadata' => $product->metadata ?? [],
                'description' => $product->description ?? '',
                'benefits' => $product->benefits ?? [],
                'price' => $price->unit_amount / 100, // Árat centből átváltjuk normál pénznemre
                'currency' => strtoupper($price->currency),
                'interval' => $interval,
                'days' => $days,
                'interval_count' => $intervalCount,
            ];

            // subplans rendezése a days serint
            usort($subscriptions[$id]['subplans'], function ($a, $b) {
                return $a['days'] <=> $b['days'];
            });

            $subscriptions[$id]['benefits'] = $product->benefits ?? [];
            $subscriptions[$id]['name'] = $product->name;
        }

        return response()->json($subscriptions);
    }

    public function createCheckoutSession(Request $request)
    {

        $frontend_url = env('FRONTEND_URL', 'http://localhost:4200');

        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        $checkoutSession = $request->user()
            ->newSubscription(env('APP_NAME', 'Subscription'), $validated['price_id'])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $frontend_url . "/user/success-subscription?session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => $frontend_url . "/user/cancel-subscription",
            ]);

        return response()->json([
            'url' => $checkoutSession->url,
            'session_id' => $checkoutSession->id,
        ]);
    }

    public function saveSubscription(Request $request)
    {
        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            // Stripe Checkout Session lekérése
            $session = \Stripe\Checkout\Session::retrieve($request->session_id);

            if (!$session || !isset($session->customer, $session->subscription)) {
                return response()->json(['error' => 'Invalid session data'], 400);
            }

            // Felhasználó keresése Stripe ID alapján
            $user = User::where('stripe_id', $session->customer)->firstOrFail();

            // Előfizetés lekérése Stripe-ból
            $stripeSubscription = \Stripe\Subscription::retrieve($session->subscription);

            if (!$stripeSubscription) {
                return response()->json(['error' => 'Subscription not found in Stripe'], 404);
            }

            $products = [];
            foreach ($stripeSubscription->items->data as $item) {
                // Lekérjük a terméket a price.product hivatkozás alapján
                $product = Product::retrieve($item->price->product);

                $products[] = [
                    'product_name' => $product->name,
                    'metadata' => $product->metadata,
                    'price' => $item->price->unit_amount / 100, // Árat centből átváltjuk
                    'currency' => strtoupper($item->price->currency),
                    'interval_count' => $item->price->recurring ? $item->price->recurring->interval_count : 1,
                    'interval' => $item->price->recurring ? $item->price->recurring->interval : 'one-time',
                ];
            }

            // Előfizetés mentése az adatbázisba
            $subscription = $user->subscriptions()->updateOrCreate(
                ['stripe_id' => $stripeSubscription->id],
                [
                    //'type' => $products[0]['metadata']->type,
                    'type' => 'default',
                    'stripe_status' => $stripeSubscription->status,
                    'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
                    'quantity' => $stripeSubscription->quantity,
                    'trial_ends_at' => $stripeSubscription->trial_end ? Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                    'ends_at' => $stripeSubscription->cancel_at ? Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
                ]
            );

            // Előfizetési tételek mentése (SubscriptionItem model)
            foreach ($stripeSubscription->items->data as $item) {
                $subscription->items()->updateOrCreate(
                    ['stripe_id' => $item->id],
                    [
                        'stripe_product' => $item->price->product,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity,
                    ]
                );
            }

            return response()->json([
                'message' => 'Subscription saved successfully',
                'subscription' => $stripeSubscription,
                'products' => $products,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancelSubscription(Request $request)
    {
        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            $subscription = $request->user()->subscription();

            $subscription->cancelNowAndInvoice();

            $subscription->delete();
            $subscription->items()->delete();

            return response()->json(['message' => 'Subscription canceled successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}