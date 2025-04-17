<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemIngredient;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $this->middleware('role:admin');

        $data = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0', // السعر للزبون
            'ingredients' => 'required|array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
        ]);

        // إنشاء العنصر مع قيمة مبدئية لـ cost
        $item = Item::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'cost' => 0, // قيمة مبدئية
            'branch_id' => auth()->user()->branch_id,
        ]);

        // إضافة المكونات
        foreach ($data['ingredients'] as $ingredientData) {
            ItemIngredient::create([
                'item_id' => $item->id,
                'ingredient_id' => $ingredientData['ingredient_id'],
                'quantity' => $ingredientData['quantity'],
            ]);
        }

        // حساب التكلفة وتحديثها
        $cost = $item->calculateCost();
        $item->update(['cost' => $cost]);

        return response()->json([
            'message' => 'Item created',
            'item' => $item,
            'cost_to_restaurant' => $cost,
            'price_to_customer' => $item->price,
        ]);
    }

    public function show($id)
    {
        $this->middleware('role:admin');

        $item = Item::with('ingredients.ingredient')->findOrFail($id);
        $cost = $item->calculateCost();

        return response()->json([
            'item' => $item,
            'cost_to_restaurant' => $cost,
            'price_to_customer' => $item->price,
        ]);
    }
}