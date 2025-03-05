<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;

class AdminStripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function getAllPlans()
    {
        $products = \Stripe\Product::all();

        foreach ($products as $product) {
            $product->prices = \Stripe\Price::all(['product' => $product->id]);
        }

        return response()->json($products);
    }

    public function createPlan(Request $request)
    {
        $product = \Stripe\Product::create([
            'name' => $request->name,
            'type' => 'service',
        ]);
        return response()->json($product);
    }

    public function getPlan($id)
    {
        $product = \Stripe\Product::retrieve($id);

        $product->prices = \Stripe\Price::all(['product' => $product->id]);

        foreach ($product->prices as $price) {
            $price->subscriptions_count = \Stripe\Subscription::all(['price' => $price->id])->count();
        }

        return response()->json($product);
    }

    public function getPrice($id)
    {
        $price = \Stripe\Price::retrieve($id);
        $price->subscriptions_count = \Stripe\Subscription::all(['price' => $price->id])->count();
        $price->product = \Stripe\Product::retrieve($price->product);
        return response()->json($price);
    }

    // #############################
    // INVOICE
    // #############################

    public function getAllInvoices()
    {
        $invoices = \Stripe\Invoice::all();
        return response()->json($invoices);
    }

    public function getInvoice($id)
    {
        $invoice = \Stripe\Invoice::retrieve($id);
        foreach ($invoice->lines->data as $line) {
            $line->plan->product_details = \Stripe\Product::retrieve($line->plan->product);
            $line->subscription_details = \Stripe\Subscription::retrieve($line->subscription);
        }
        return response()->json($invoice);
    }

    // #############################
    // SUBSCRIPTION
    // #############################

    public function getAllSubscriptions()
    {
        $subscriptions = \Stripe\Subscription::all();

        foreach ($subscriptions as $subscription) {
            $subscription->customer_details = \Stripe\Customer::retrieve($subscription->customer);
            $subscription->plan->product_details = \Stripe\Product::retrieve($subscription->plan->product);
        }

        return response()->json($subscriptions);
    }

    public function getSubscription($id)
    {
        $subscription = \Stripe\Subscription::retrieve($id);
        $subscription->customer_details = \Stripe\Customer::retrieve($subscription->customer);
        $subscription->plan->product_details = \Stripe\Product::retrieve($subscription->plan->product);
        return response()->json($subscription);
    }

}
