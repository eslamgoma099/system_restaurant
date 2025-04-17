<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function registerCustomer(Request $request)
    {
        $this->middleware('role:cashier,admin');

        $data = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|unique:customers',
            'email' => 'nullable|email|unique:customers',
        ]);

        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'branch_id' => auth()->user()->branch_id,
        ]);

        return response()->json(['message' => 'Customer registered', 'customer' => $customer]);
    }

    public function earnPoints(Request $request)
    {
        $this->middleware('role:cashier');

        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = \App\Models\Order::findOrFail($data['order_id']);
        if ($order->payment_status !== 'paid') {
            return response()->json(['message' => 'Order must be paid to earn points'], 400);
        }

        $points = floor($order->total_price / 10); // 1 نقطة لكل 10 وحدات نقدية

        $loyaltyPoint = LoyaltyPoint::create([
            'customer_id' => $data['customer_id'],
            'points' => $points,
            'order_id' => $data['order_id'],
            'earned_at' => now(),
        ]);

        return response()->json(['message' => 'Points earned', 'loyalty_point' => $loyaltyPoint]);
    }

    public function getCustomerPoints($customerId)
    {
        $this->middleware('role:cashier,admin');

        $customer = Customer::findOrFail($customerId);
        $totalPoints = $customer->loyaltyPoints()->sum('points');

        return response()->json([
            'customer' => $customer,
            'total_points' => $totalPoints,
        ]);
    }
}