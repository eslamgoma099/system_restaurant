<?php
namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\SupplyRequest;
use App\Notifications\SupplyRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
class SupplyRequestController extends Controller
{
    public function autoRequest()
    {
        $this->middleware('role:admin');

        $threshold = 10;
        $minOrderQuantity = 20;
        $branchId = auth()->user()->branch_id;

        $lowStockItems = Inventory::where('branch_id', $branchId)
            ->where('quantity', '<=', $threshold)
            ->with('item.ingredients.ingredient')
            ->get();

        $requests = [];
        foreach ($lowStockItems as $inventory) {
            foreach ($inventory->item->ingredients as $itemIngredient) {
                $request = SupplyRequest::create([
                    'ingredient_id' => $itemIngredient->ingredient_id,
                    'quantity' => $minOrderQuantity,
                    'branch_id' => $branchId,
                    'status' => 'pending',
                    'requested_at' => now(),
                ]);

                // إرسال إشعار بالبريد الإلكتروني
                Notification::route('mail', auth()->user()->email)
                            ->notify(new SupplyRequestNotification($itemIngredient->ingredient->name, $minOrderQuantity));

                $requests[] = [
                    'ingredient_name' => $itemIngredient->ingredient->name,
                    'quantity' => $minOrderQuantity,
                ];
            }
        }

        return response()->json(['message' => 'Supply requests created', 'requests' => $requests]);
    }

    public function index()
    {
        $this->middleware('role:admin');

        $requests = SupplyRequest::where('branch_id', auth()->user()->branch_id)
            ->with('ingredient')
            ->get();

        return response()->json(['supply_requests' => $requests]);
    }

    public function updateStatus(Request $request, $id)
    {
        $this->middleware('role:admin');

        $supplyRequest = SupplyRequest::findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:pending,approved,delivered',
        ]);

        $supplyRequest->update(['status' => $data['status']]);

        // إذا تم التسليم، تحديث المخزون
        if ($data['status'] === 'delivered') {
            $inventory = Inventory::where('branch_id', $supplyRequest->branch_id)
                ->whereHas('item.ingredients', function ($query) use ($supplyRequest) {
                    $query->where('ingredient_id', $supplyRequest->ingredient_id);
                })->first();

            if ($inventory) {
                $inventory->increment('quantity', $supplyRequest->quantity);
            }
        }

        return response()->json(['message' => 'Supply request updated', 'request' => $supplyRequest]);
    }
}