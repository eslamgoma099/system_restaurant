<?php

namespace App\Http\Controllers;

use App\Models\MainItem;
use Illuminate\Http\Request;

class MainItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin');
    }

    public function index()
    {
        $mainItems = MainItem::where('branch_id', auth()->user()->branch_id)->get();
        return response()->json($mainItems);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $mainItem = MainItem::create([
            'name' => $data['name'],
            'branch_id' => auth()->user()->branch_id,
        ]);

        return response()->json($mainItem, 201);
    }

    public function show(MainItem $mainItem)
    {
        // Ensure the main item belongs to the user's branch
        if ($mainItem->branch_id !== auth()->user()->branch_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return response()->json($mainItem);
    }

    public function update(Request $request, MainItem $mainItem)
    {
        if ($mainItem->branch_id !== auth()->user()->branch_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $mainItem->update($data);

        return response()->json($mainItem);
    }

    public function destroy(MainItem $mainItem)
    {
        if ($mainItem->branch_id !== auth()->user()->branch_id) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $mainItem->delete();

        return response()->json(['message' => 'Main item deleted']);
    }
}
