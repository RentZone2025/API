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
use Symfony\Component\ErrorHandler\Debug;

class UserController extends Controller
{

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // REMOVE ACCOUNT
    }
}
