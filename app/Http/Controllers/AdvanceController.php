<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Expense;
use Illuminate\Http\Request;

class AdvanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin')->except(['requestAdvance']);
        $this->middleware('role:employee,cashier')->only(['requestAdvance']);
    }

    public function requestAdvance(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $advance = Expense::create([
            'shift_id' => $data['shift_id'],
            'user_id' => $user->id,
            'type' => 'advance',
            'amount' => $data['amount'],
            'description' => $data['description'],
        ]);

        return response()->json([
            'message' => 'تم تقديم طلب السلفة بنجاح',
            'advance' => $advance,
        ]);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $query = Expense::where('type', 'advance');

        if (isset($data['start_date'])) {
            $query->where('created_at', '>=', $data['start_date']);
        }
        if (isset($data['end_date'])) {
            $query->where('created_at', '<=', $data['end_date']);
        }
        if (isset($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin') {
            $query->whereHas('user', fn($q) => $q->where('branch_id', $authUser->branch_id));
        }

        $advances = $query->with('user')->get();

        return response()->json([
            'message' => 'تقرير السلف',
            'advances' => $advances,
            'total_amount' => number_format($advances->sum('amount'), 2),
        ]);
    }
}