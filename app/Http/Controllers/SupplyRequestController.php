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
    public function store(Request $request)
    {
        $this->middleware('role:admin');

        $data = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0',
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'required|string|max:255',
            'supplier_phone' => 'required|string|max:20',
            'supplier_email' => 'required|email|max:255',
            'requested_at' => 'required|date',
            'price' => 'required|numeric|min:0',
        ]);

        // تحويل الصيغة إذا كانت غير صحيحة
        $data['requested_at'] = date('Y-m-d', strtotime($data['requested_at']));

        $branchId = auth()->user()->branch_id;

        $supplyRequest = SupplyRequest::create([
            'ingredient_id' => $data['ingredient_id'],
            'quantity' => $data['quantity'],
            'branch_id' => $branchId,
            'status' => 'pending',
            'requested_at' => $data['requested_at'],
            'supplier_name' => $data['supplier_name'],
            'supplier_address' => $data['supplier_address'],
            'supplier_phone' => $data['supplier_phone'],
            'supplier_email' => $data['supplier_email'],
            'price' => $data['price'],
        ]);

        return response()->json(['message' => 'Supply request created successfully.', 'data' => $supplyRequest], 201);
    }

    public function autoRequest()
    {
        // $this->middleware('role:admin');

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
                ->where('ingredient_id', $supplyRequest->ingredient_id)
                ->first();

            if ($inventory) {
                $inventory->increment('quantity', $supplyRequest->quantity);
            } else {
                // إذا لم يوجد سجل، أنشئ واحد جديد
                Inventory::create([
                    'branch_id' => $supplyRequest->branch_id,
                    'ingredient_id' => $supplyRequest->ingredient_id,
                    'quantity' => $supplyRequest->quantity,
                    'last_updated' => now(),
                ]);
            }

            // إذا أردت تحديث item_ingredients بناءً على التوريد (غير شائع)
            // ItemIngredient::where('ingredient_id', $supplyRequest->ingredient_id)
            //     ->update(['quantity' => ...]);
        }

        return response()->json(['message' => 'Supply request updated', 'request' => $supplyRequest]);
    }
}