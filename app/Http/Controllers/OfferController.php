<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function store(Request $request)
    {
        $this->middleware('role:admin');

        $data = $request->validate([
            'name' => 'required|string',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $offer = Offer::create([
            'name' => $data['name'],
            'discount_percentage' => $data['discount_percentage'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'branch_id' => auth()->user()->branch_id,
        ]);

        return response()->json(['message' => 'Offer created', 'offer' => $offer]);
    }

    public function index()
    {
        $this->middleware('role:admin,cashier');

        $offers = Offer::where('branch_id', auth()->user()->branch_id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        return response()->json(['offers' => $offers]);
    }
}