<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function store(Request $request)
    {
        // تأكد أن المستخدم له الصلاحية
        $this->middleware('role:admin');

        // تحقق من البيانات المدخلة
        $data = $request->validate([
            'name' => 'required|string|unique:ingredients,name',
            'unit' => 'required|string',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        // إنشاء المكون وربطه بالفرع التابع للمستخدم
        $ingredient = Ingredient::create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'cost_per_unit' => $data['cost_per_unit'],
            'branch_id' => auth()->user()->branch_id,
        ]);

        return response()->json([
            'message' => 'Ingredient created successfully',
            'ingredient' => $ingredient
        ], 201);
    }
}
