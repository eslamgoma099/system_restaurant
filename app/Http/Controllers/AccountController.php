<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\User;
use App\Models\Shift;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use App\Exports\FinancialReportExport;
use Maatwebsite\Excel\Facades\Excel;

class AccountController extends Controller
{

    public function store(Request $request)
    {
        $this->middleware('role:admin,super_admin');

        $data = $request->validate([
            'type' => 'required|in:revenue,expense',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        // تحويل التاريخ إلى التنسيق الصحيح
        $formattedDate = Carbon::parse($data['date'])->format('Y-m-d');

        $account = Account::create([
            'branch_id' => auth()->user()->branch_id,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'date' => $formattedDate,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Account entry created', 'account' => $account]);
    }


    public function index()
{
    $this->middleware('role:admin,super_admin');

    // جلب كل الحسابات المرتبطة بفرع المستخدم
    $accounts = Account::where('branch_id', auth()->user()->branch_id)->get();

    // حساب الإيرادات
    $revenue = $accounts->where('type', 'revenue')->sum('amount');

    // حساب المصروفات
    $expenses = $accounts->where('type', 'expense')->sum('amount');

    // حساب الربح أو الخسارة
    $profit = $revenue - $expenses;

    return response()->json([
        'accounts' => $accounts,
        'summary' => [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'profit' => $profit
        ]
    ]);
}
//    return response()->json(['accounts' => $accounts]);
//     }

public function summary(Request $request)
{
    $this->middleware('role:admin,super_admin');

    $startDate = Carbon::parse($request->query('start_date', now()->startOfMonth()));
    $endDate = Carbon::parse($request->query('end_date', now()->endOfDay()));

    // الإحصائيات الإجمالية
    $accountExpenses = Account::where('type', 'expense')
        ->whereBetween('date', [$startDate, $endDate])
        ->sum('amount');

    $accountRevenue = Account::where('type', 'revenue')
        ->whereBetween('date', [$startDate, $endDate])
        ->sum('amount');

    $orderSales = Payment::whereBetween('payment_date', [$startDate, $endDate])
        ->sum('amount');

    $shiftExpenses = Expense::whereBetween('created_at', [$startDate, $endDate])
        ->sum('amount');

    $shiftPayments = Payment::whereBetween('payment_date', [$startDate, $endDate])
        ->sum('amount');

    $totalRevenue = $accountRevenue + $orderSales + $shiftPayments;
    $totalExpenses = $accountExpenses + $shiftExpenses;
    $profit = $totalRevenue - $totalExpenses;

    // الإحصائيات العددية
    $totalUsers = User::count();
    $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
    $totalShifts = Shift::whereBetween('start_time', [$startDate, $endDate])->count();
    $totalExpensesCount = Expense::whereBetween('created_at', [$startDate, $endDate])->count();
    $totalPaymentsCount = Payment::whereBetween('payment_date', [$startDate, $endDate])->count();

    // البيانات اليومية (trend)
    $dateRange = new \DatePeriod($startDate, \DateInterval::createFromDateString('1 day'), $endDate->copy()->addDay());
    $dailyStats = [];

    foreach ($dateRange as $date) {
        $day = $date->format('Y-m-d');

        $dailyRevenue = Account::where('type', 'revenue')->whereDate('date', $day)->sum('amount')
            + Payment::whereDate('payment_date', $day)->sum('amount');

        $dailyExpense = Account::where('type', 'expense')->whereDate('date', $day)->sum('amount')
            + Expense::whereDate('created_at', $day)->sum('amount');

        $dailyStats[] = [
            'date' => $day,
            'revenue' => $dailyRevenue,
            'expenses' => $dailyExpense,
            'profit' => $dailyRevenue - $dailyExpense,
        ];
    }

    return response()->json([
        'period' => [
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
        ],
        'summary' => [
            'account_revenue' => $accountRevenue,
            'account_expenses' => $accountExpenses,
            'order_sales' => $orderSales,
            'shift_payments' => $shiftPayments,
            'shift_expenses' => $shiftExpenses,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'profit' => $profit,
        ],
        'stats' => [
            'total_users' => $totalUsers,
            'total_orders' => $totalOrders,
            'total_shifts' => $totalShifts,
            'total_expenses' => $totalExpensesCount,
            'total_payments' => $totalPaymentsCount,
        ],
        'trends' => $dailyStats,
    ]);
}
// public function exportFinancialReport(Request $request)
// {
//     // استخراج تاريخ البداية والنهاية من الطلب، أو تعيين قيم افتراضية
//     $startDate = Carbon::parse($request->query('start_date', now()->startOfMonth()));
//     $endDate = Carbon::parse($request->query('end_date', now()->endOfDay()));
//     $dateRange = new \DatePeriod($startDate, \DateInterval::createFromDateString('1 day'), $endDate->copy()->addDay());

//     $dailyStats = [];

//     foreach ($dateRange as $date) {
//         $day = $date->format('Y-m-d');

//         $dailyRevenue = Account::where('type', 'revenue')->whereDate('date', $day)->sum('amount')
//             + Payment::whereDate('payment_date', $day)->sum('amount');

//         $dailyExpense = Account::where('type', 'expense')->whereDate('date', $day)->sum('amount')
//             + Expense::whereDate('created_at', $day)->sum('amount');

//         $dailyStats[] = [
//             'date' => $day,
//             'revenue' => $dailyRevenue,
//             'expenses' => $dailyExpense,
//             'profit' => $dailyRevenue - $dailyExpense,
//         ];
//     }

//     return Excel::download(new FinancialReportExport($dailyStats), 'financial_report.xlsx');
// }

}