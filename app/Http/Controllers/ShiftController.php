<?php
namespace App\Http\Controllers;

use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Models\Expense;
use Illuminate\Http\Request;
use App\Models\ShiftTemplate;

class ShiftController extends Controller
{
    public function startShift(Request $request)
    {
        $this->middleware('role:cashier');

        $data = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'shift_template_id' => 'nullable|exists:shift_templates,id', // ✅ تم إضافته
        ]);

        $activeShift = Shift::where('cashier_id', auth()->id())
            ->whereNull('end_time')
            ->first();

        if ($activeShift) {
            return response()->json(['message' => 'You already have an active shift', 'shift' => new ShiftResource($activeShift)], 400);
        }

        // التحقق من الشفت السابق
        $previousShift = Shift::where('cashier_id', auth()->id())
            ->whereNotNull('end_time')
            ->orderBy('end_time', 'desc')
            ->first();

        $warning = null;
        if ($previousShift && $previousShift->final_balance != $data['opening_balance']) {
            $warning = "Opening balance ({$data['opening_balance']}) does not match previous shift's final balance ({$previousShift->final_balance})";
        }

        $shift = Shift::create([
            'cashier_id' => auth()->id(),
            'opening_balance' => $data['opening_balance'],
            'start_time' => now(),
            'shift_template_id' => $data['shift_template_id'], // جديد
        ]);

        $shift->final_balance = $shift->calculateFinalBalance();
        $shift->save();

        $response = [
            'message' => 'Shift started',
            'shift' => new ShiftResource($shift),
        ];

        if ($warning) {
            $response['warning'] = $warning;
        }

        return response()->json($response);
    }

    public function endShift(Request $request, $id)
    {
        $this->middleware('role:cashier');

        $shift = Shift::where('cashier_id', auth()->id())->find($id);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found or does not belong to you'], 404);
        }

        if ($shift->end_time) {
            return response()->json(['message' => 'Shift already ended'], 400);
        }

        $shift->update([
            'end_time' => now(),
            'total_payments' => $shift->payments()->sum('amount'),
            'total_expenses' => $shift->expenses()->sum('amount'),
        ]);

        $shift->final_balance = $shift->calculateFinalBalance();
        $extraMinutes = $shift->calculateExtraTime();
        $shift->extra_minutes = $extraMinutes;
        $shift->save();

        return response()->json(['message' => 'Shift ended', 'shift' => new ShiftResource($shift)]);
    }

    public function addExpense(Request $request, $shiftId)
    {
        $this->middleware('role:cashier');

        $shift = Shift::where('cashier_id', auth()->id())->find($shiftId);

        if (!$shift) {
            return response()->json(['message' => 'Shift not found or does not belong to you'], 404);
        }

        if ($shift->end_time) {
            return response()->json(['message' => 'Cannot add expense to an ended shift'], 400);
        }

        $data = $request->validate([
            'type' => 'required|in:expense,advance',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',

        ]);

        $expense = Expense::create([
            'shift_id' => $shift->id,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
        ]);

        $shift->total_expenses = $shift->expenses()->sum('amount');
        $shift->final_balance = $shift->calculateFinalBalance();
        $shift->save();

        return response()->json(['message' => 'Expense added', 'expense' => $expense]);
    }

    public function index()
    {
        $this->middleware('role:cashier,admin');

        $shifts = Shift::where('cashier_id', auth()->id())->get();
        return ShiftResource::collection($shifts);
    }
    public function shiftReport(Request $request)
{
    $this->middleware('role:cashier,admin,super_admin');

    $data = $request->validate([
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
    ]);

    $query = Shift::where('cashier_id', auth()->id())
                  ->with(['payments', 'expenses']);

    if ($request->start_date) {
        $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
        $query->where('start_time', '>=', $startDate);
    }

    if ($request->end_date) {
        $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
        $query->where('end_time', '<=', $endDate);
    }

    $shifts = $query->get();

    // تحديث الرصيد النهائي لكل وردية
    $shifts->each(function ($shift) {
        $shift->calculateFinalBalance();
    });

    $totalPayments = $shifts->sum('total_payments');
    $totalExpenses = $shifts->sum('total_expenses');

    // تفاصيل المدفوعات حسب طريقة الدفع
    $paymentMethodsBreakdown = [];
    foreach ($shifts as $shift) {
        $payments = $shift->getPaymentsForShift()->get();
        foreach ($payments as $payment) {
            $method = $payment->payment_method;
            if (!isset($paymentMethodsBreakdown[$method])) {
                $paymentMethodsBreakdown[$method] = 0;
            }
            $paymentMethodsBreakdown[$method] += $payment->amount;
        }
    }

    $paymentMethodsBreakdownFormatted = [];
    foreach ($paymentMethodsBreakdown as $method => $amount) {
        $paymentMethodsBreakdownFormatted[] = [
            'payment_method' => $method,
            'total_amount' => number_format($amount, 2),
        ];
    }

    // تفاصيل المصروفات حسب النوع
    $expenseTypesBreakdown = [];
    foreach ($shifts as $shift) {
        foreach ($shift->expenses as $expense) {
            $type = $expense->type ?? 'غير محدد';
            if (!isset($expenseTypesBreakdown[$type])) {
                $expenseTypesBreakdown[$type] = 0;
            }
            $expenseTypesBreakdown[$type] += $expense->amount;
        }
    }

    $expenseTypesBreakdownFormatted = [];
    foreach ($expenseTypesBreakdown as $type => $amount) {
        $expenseTypesBreakdownFormatted[] = [
            'expense_type' => $type,
            'total_amount' => number_format($amount, 2),
        ];
    }

    $report = [
        'total_shifts' => $shifts->count(),
        'total_opening_balance' => number_format($shifts->sum('opening_balance'), 2),
        'total_payments' => number_format($totalPayments, 2),
        'payment_methods_breakdown' => $paymentMethodsBreakdownFormatted,
        'total_expenses' => number_format($totalExpenses, 2),
        'expense_types_breakdown' => $expenseTypesBreakdownFormatted,
        'total_final_balance' => number_format($shifts->sum('final_balance'), 2),
        'shifts' => ShiftResource::collection($shifts),
    ];

    return response()->json(['message' => 'Shift report generated', 'report' => $report]);
}


public function dailyReport(Request $request)
{
    $this->middleware('role:admin');

    $date = $request->input('date', now()->toDateString());
    $shifts = Shift::whereDate('start_time', $date)->with(['payments', 'expenses'])->get();

    $report = [
        'date' => $date,
        'total_shifts' => $shifts->count(),
        'total_opening_balance' => number_format($shifts->sum('opening_balance'), 2),
        'total_payments' => number_format($shifts->sum('total_payments'), 2),
        'total_expenses' => number_format($shifts->sum('total_expenses'), 2),
        'total_final_balance' => number_format($shifts->sum('final_balance'), 2),
        'shifts' => ShiftResource::collection($shifts),
    ];

    return response()->json(['message' => 'Daily shift report generated', 'report' => $report]);
}
}