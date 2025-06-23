<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function store(Request $request)
    {
        // تحقق من البيانات المدخلة
        $data = $request->validate([
            'name' => 'required|string|unique:ingredients,name',
            'unit' => 'required|string',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        // التحقق من وجود branch_id للمستخدم
        $user = auth()->user();
        if (!$user->branch_id) {
            return response()->json([
                'message' => 'User must be assigned to a branch'
            ], 400);
        }

        // إنشاء المكون وربطه بالفرع التابع للمستخدم
        $ingredient = Ingredient::create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'cost_per_unit' => $data['cost_per_unit'],
            'branch_id' => $user->branch_id,
        ]);

        return response()->json([
            'message' => 'Ingredient created successfully',
            'ingredient' => $ingredient
        ], 201);
    }
}
