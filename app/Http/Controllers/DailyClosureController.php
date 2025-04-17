<?php
namespace App\Http\Controllers;

use App\Models\DailyClosure;
use App\Models\Payment;
use Illuminate\Http\Request;

class DailyClosureController extends Controller
{
    public function store(Request $request)
    {
        $this->middleware('role:cashier,admin');

        $cashierId = auth()->id();
        $branchId = auth()->user()->branch_id;
        $today = now()->startOfDay();

        // حساب المدفوعات اليومية
        $totalCash = Payment::where('cashier_id', $cashierId)
            ->where('branch_id', $branchId)
            ->where('payment_date', '>=', $today)
            ->where('payment_method', 'cash')
            ->sum('amount');

        $totalCard = Payment::where('cashier_id', $cashierId)
            ->where('branch_id', $branchId)
            ->where('payment_date', '>=', $today)
            ->where('payment_method', 'card')
            ->sum('amount');

        $totalRevenue = $totalCash + $totalCard;

        $closure = DailyClosure::create([
            'branch_id' => $branchId,
            'cashier_id' => $cashierId,
            'total_cash' => $totalCash,
            'total_card' => $totalCard,
            'total_revenue' => $totalRevenue,
            'closure_date' => now(),
        ]);

        return response()->json(['message' => 'Daily closure completed', 'closure' => $closure]);
    }

    public function index()
    {
        $this->middleware('role:admin,super_admin');

        $closures = DailyClosure::where('branch_id', auth()->user()->branch_id)->get();
        return response()->json(['closures' => $closures]);
    }
}