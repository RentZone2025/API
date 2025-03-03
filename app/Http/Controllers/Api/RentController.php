<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rent;
use Illuminate\Support\Facades\Auth;

class RentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        // Betöltjük a kapcsolódó rent_items és items adatokat
        $rents = Rent::with('rentItems.item')->whereNull('deleted_at')->where('user_id', $user->id)->get();

        return response()->json($rents);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Új Rent létrehozása
        $rent = Rent::create($request->all());

        // Ha vannak rent_items, azokat is hozzáadjuk
        if ($request->has('rent_items')) {
            foreach ($request->input('rent_items') as $rentItem) {
                $rent->rentItems()->create($rentItem);
            }
        }

        // Visszatérünk a kapcsolatokkal együtt
        return response()->json($rent->load('rentItems.item'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // A megadott Rent lekérdezése kapcsolatokkal együtt
        $rent = Rent::with('rentItems.item')->findOrFail($id);

        return response()->json($rent);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // A megadott Rent frissítése
        $rent = Rent::findOrFail($id);
        $rent->update($request->all());

        // Kapcsolódó rent_items frissítése (ha vannak)
        if ($request->has('rent_items')) {
            foreach ($request->input('rent_items') as $updatedRentItem) {
                $rentItem = $rent->rentItems()->find($updatedRentItem['id']);
                if ($rentItem) {
                    $rentItem->update($updatedRentItem);
                }
            }
        }

        return response()->json($rent->load('rentItems.item'));
    }

    public function getArchives(){
        $user = Auth::user();

        // Betöltjük a kapcsolódó rent_items és items adatokat
        $rents = Rent::with('rentItems.item')->whereNotNull('deleted_at')->where('user_id', $user->id)->get();

        return response()->json($rents);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // A megadott Rent törlése
        $rent = Rent::findOrFail($id);
        
        // Kapcsolódó rent_items törlése
        $rent->rentItems()->delete();
        
        $rent->delete();

        return response()->json(null, 204);
    }
}