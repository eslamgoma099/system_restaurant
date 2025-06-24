<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemIngredient;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Ingredient;
use App\Models\MainItem;

class ItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin');
    }

    public function store(Request $request)
    {
        // $this->middleware('role:admin');

        $request->mergeIfMissing(['main_item_id' => $request->input('main-item_id')]);

        $data = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric|min:0', // السعر للزبون
            'ingredients' => 'required|array',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity' => 'required|numeric|min:0',
            'main_item_id' => 'nullable|exists:main_items,id',
            'size' => 'nullable|in:small,medium,large,family',
        ]);

        // إنشاء العنصر مع قيمة مبدئية لـ cost
        $item = Item::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'cost' => 0, // قيمة مبدئية
            'branch_id' => auth()->user()->branch_id,
            'main_item_id' => $data['main_item_id'] ?? null,
            'size' => $data['size'] ?? null,


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
            'main_item' => $item->mainItem,
            'size' => $item->size,
            'ingredients' => $item->ingredients,
            'cost_to_restaurant' => $cost,
            'price_to_customer' => $item->price,
            'main_item_id' => $item->main_item_id
        ]);
    }

    public function show($id)
    {
        // $this->middleware('role:admin');

        $item = Item::with('ingredients.ingredient')->findOrFail($id);
        $cost = $item->calculateCost();

        return response()->json([
            'item' => $item,
            'cost_to_restaurant' => $cost,
            'price_to_customer' => $item->price,
        ]);
    }

    public function index()
    {
        // $this->middleware('role:admin,cashier,employee');

        $items = Item::where('branch_id', auth()->user()->branch_id)
            ->with(['ingredients.ingredient', 'mainItem'])
            ->get();

        return response()->json([
            'items' => $items
        ]);
    }
}