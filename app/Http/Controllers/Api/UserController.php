<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BillingDetail;
use App\Models\ShippingDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Log;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Product;

class UserController extends Controller
{

    public function index(Request $request)
    {

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $user = $request->user()->loadMissing(['shipping', 'billing']);

        // Ellenőrizzük, hogy van-e aktív előfizetés
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
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            "fullname" => "required|string|max:255",
            "email" => "required|email|max:255|unique:users,email," . $id,
            "phone_number" => "required|string|max:20",
        ]);

        $user = User::findOrFail($id);
        $user->update($validated);

        return response()->json($user);
    }

    public function changePassword(Request $request)
    {

        $user = Auth::user();

        $validated = $request->validate([
            'old_password' => ['required', 'string', 'min:8'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:old_password'],
        ]);

        //$user = User::findOrFail($id);

        if (!Hash::check($validated['old_password'], $user->password)) {
            return response()->json(['message' => 'A régi jelszó nem megfelelő.'], 400);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json(['message' => 'A jelszó sikeresen megváltoztatva.'], 200);
    }

    public function changeBilling(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
        ]);

        $billing = BillingDetail::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        )->makeHidden(['created_at', 'updated_at']);

        return response()->json(['message' => 'Billing details updated successfully', 'billing' => $billing]);
    }

    public function changeShipping(Request $request)
    {

        Log::debug($request);

        $user = Auth::user();

        $validated = $request->validate([
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
        ]);

        $shipping = ShippingDetail::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        )->makeHidden(['created_at', 'updated_at']);

        return response()->json(['message' => 'Shipping details updated successfully', 'shipping' => $shipping]);
    }

    public function suspend()
    {

        $user = Auth::user();

        // delete profile
        $user = User::findOrFail($user->id);
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // delete profile
        $user = User::findOrFail($id);
        $user->forceDelete();

        return response()->json(null, 204);
    }
}
