<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Item;
use Illuminate\Http\Request;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;

class InventoryController extends Controller
{
    public function index()
{
    $this->middleware('role:admin,cashier');

    $inventory = Inventory::where('branch_id', auth()->user()->branch_id)
        ->with('item')
        ->get();

    foreach ($inventory as $item) {
        if ($item->quantity <= 10) {
            // auth()->user()->notify(new LowStockNotification($item->item->name, $item->quantity));
        }
    }

//     return InventoryResource::collection($inventory);
// }
        return response()->json(['inventory' => $inventory]);
    }

    public function updateStock(Request $request, $itemId)
    {
        $this->middleware('role:admin');

        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $item = Item::findOrFail($itemId);
        $inventory = Inventory::updateOrCreate(
            ['item_id' => $item->id, 'branch_id' => auth()->user()->branch_id],
            ['quantity' => $data['quantity'], 'last_updated' => now()]
        );

        return response()->json(['message' => 'Stock updated', 'inventory' => $inventory]);
    }
//     public function lowStockAlerts()
// {
//     $lowStockItems = Inventory::where('quantity', '<', 10)->get();
//     foreach ($lowStockItems as $item) {
//         $admins = User::whereHas('role', fn($q) => $q->where('name', 'admin'))->get();
//         foreach ($admins as $admin) {
//             $admin->notify(new \App\Notifications\LowStockNotification($item->item->name, $item->quantity));
//         }
//     }
//     return response()->json(['low_stock_items' => $lowStockItems]);
// }

    public function lowStockAlerts()
    {
        $this->middleware('role:admin');

        $threshold = 10;
        $lowStock = Inventory::where('branch_id', auth()->user()->branch_id)
            ->where('quantity', '<=', $threshold)
            ->with('item')
            ->get();

        $alerts = $lowStock->map(function ($entry) {
            // إرسال إشعار بالبريد الإلكتروني
            // Notification::route('mail', auth()->user()->email)
            //             ->notify(new LowStockNotification($entry->item->name, $entry->quantity));

            return [
                'item_name' => $entry->item->name,
                'current_quantity' => $entry->quantity,
                'threshold' => 10,
                'message' => "Stock for {$entry->item->name} is low ({$entry->quantity} remaining)",
            ];
        });

        return response()->json(['low_stock_alerts' => $alerts]);
    }
}