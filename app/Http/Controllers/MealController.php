<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Ingredient;
class MealController extends Controller
{
    public function store(Request $request)
    {
        $this->middleware('role:admin');

        $data = $request->validate([
            'name' => 'required|string',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'addons' => 'nullable|array',
            'addons.*.ingredient_id' => 'required|exists:ingredients,id',
            'addons.*.quantity' => 'required|numeric|min:0',
        ]);

        $meal = Meal::create([
            'name' => $data['name'],
            'branch_id' => auth()->user()->branch_id,
        ]);

        // ربط الأصناف
        foreach ($data['items'] as $item) {
            $meal->items()->attach($item['item_id'], ['quantity' => $item['quantity']]);
        }

        // ربط الإضافات
        if (!empty($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                $meal->addons()->attach($addon['ingredient_id'], ['quantity' => $addon['quantity']]);
            }
        }

        return response()->json([
            'message' => 'تم إنشاء الوجبة بنجاح',
            'meal' => $meal->load('items', 'addons')
        ]);
    }
    public function index()
    {
        $meals = Meal::where('branch_id', auth()->user()->branch_id)->get();
        $items = Item::where('branch_id', auth()->user()->branch_id)->get();
        $addons = Ingredient::where('branch_id', auth()->user()->branch_id)->get();

        return response()->json([
            'meals' => $meals,
            'items' => $items,
            'addons' => $addons,
        ]);
    }
    public function show($id)
    {
        $meal = Meal::findOrFail($id);
        return response()->json([
            'meal' => $meal,
            'items' => $meal->items,
            'addons' => $meal->addons,

        ]);
    }
    public function update(Request $request, $id)
    {
        $meal = Meal::findOrFail($id);
        $meal->update($request->all());
        return response()->json([
            'message' => 'تم تحديث الوجبة بنجاح',
            'meal' => $meal,
            'items' => $meal->items,
            'addons' => $meal->addons,
        ]);
    }
    public function destroy($id)
    {
        $meal = Meal::findOrFail($id);
        $meal->delete();
        return response()->json([
            'message' => 'تم حذف الوجبة بنجاح',
        ]);
    }
}
