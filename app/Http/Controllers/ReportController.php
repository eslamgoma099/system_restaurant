<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\DailyClosure;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $this->middleware('role:admin,super_admin');
        $branchId = auth()->user()->branch_id;
        $startDate = $request->query('start_date', now()->startOfMonth());
        $endDate = $request->query('end_date', now()->endOfMonth());
        $sales = Order::where('branch_id', $branchId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->with('items')
            ->get();
        $totalSales = $sales->sum('total_price');
        return response()->json([
            'sales' => $sales,
            'total_sales' => $totalSales,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }
    public function financialReport(Request $request)
    {
        $this->middleware('role:super_admin');
        $startDate = $request->query('start_date', now()->startOfMonth());
        $endDate = $request->query('end_date', now()->endOfMonth());
        $revenue = Account::where('type', 'revenue')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        $expenses = Account::where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        $profit = $revenue - $expenses;
        return response()->json([
            'revenue' => $revenue,
            'expenses' => $expenses,
            'profit' => $profit,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }
    public function dailyClosureReport(Request $request)
    {
        $this->middleware('role:admin,super_admin');
        $branchId = auth()->user()->branch_id;
        $startDate = $request->query('start_date', now()->startOfMonth());
        $endDate = $request->query('end_date', now()->endOfMonth());
        $closures = DailyClosure::where('branch_id', $branchId)
            ->whereBetween('closure_date', [$startDate, $endDate])
            ->get();

        $totalCash = $closures->sum('total_cash');
        $totalCard = $closures->sum('total_card');
        $totalRevenue = $closures->sum('total_revenue');
        return response()->json([
            'closures' => $closures,
            'total_cash' => $totalCash,
            'total_card' => $totalCard,
            'total_revenue' => $totalRevenue,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }

    public function itemSalesAnalysis(Request $request)
    {
        $this->middleware('role:admin,super_admin');
        $branchId = auth()->user()->branch_id;
        $startDate = $request->query('start_date', now()->startOfMonth());
        $endDate = $request->query('end_date', now()->endOfMonth());
        $items = \App\Models\Item::where('branch_id', $branchId)
            ->withCount(['orderItems' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('payment_status', 'paid');
                });
            }])
            ->with(['orderItems' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('payment_status', 'paid');
                });
            }])
            ->get();

        $analysis = $items->map(function ($item) {
            $totalQuantity = $item->orderItems->sum('quantity');
            $totalRevenue = $item->orderItems->sum(function ($orderItem) {
                return $orderItem->quantity * $orderItem->price;
            });
            $totalCost = $item->orderItems->sum(function ($orderItem) use ($item) {
                return $orderItem->quantity * $item->cost;
            });
            $profit = $totalRevenue - $totalCost;

            return [
                'item_name' => $item->name,
                'total_quantity_sold' => $totalQuantity,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'profit' => $profit,
            ];
        });

        return response()->json([
            'analysis' => $analysis,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }
}