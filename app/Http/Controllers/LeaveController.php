<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Leave;
use App\Models\OfficialHoliday;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin')->except(['requestLeave']);
        $this->middleware('role:employee,cashier')->only(['requestLeave']);
    }

    public function requestLeave(Request $request)
    {
        $data = $request->validate([
            'employee_code' => 'required|exists:users,employee_code',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $user = User::where('employee_code', $data['employee_code'])->first();

        // حساب مدة الإجازة بالأيام
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $duration = $endDate->diffInDays($startDate) + 1; // +1 لتضمين اليوم الأخير

        // التحقق من رصيد الإجازات
        $leaveBalance = $user->leave_balance;
        $deductionAmount = 0;
        $deductionAmount = 0;
        if ($leaveBalance < $duration) {
            // الرصيد غير كافٍ، احسب الفرق وحول الإجازة إلى خصم
            $excessDays = $duration - $leaveBalance;
            $deductionAmount = $user->calculateDeduction($excessDays);

            // تقليل الرصيد إلى 0 (لأننا سنستخدم كل الرصيد المتاح)
            $user->leave_balance = 0;
        } else {
            // الرصيد كافٍ، اطرح مدة الإجازة من الرصيد
            $user->leave_balance -= $duration;
        }

        // حفظ التغييرات على المستخدم
        $user->save();

        // إنشاء طلب الإجازة
        $leave = Leave::create([
            'user_id' => $user->id,
            'employee_code' => $data['employee_code'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration' => $duration,
            'status' => 'pending', // أو يمكنك وضع 'approved' مباشرة إذا كنت لا تحتاج موافقة
            'deduction_amount' => $deductionAmount,
            'days' => $duration
        ]);

        // تحويل التواريخ إلى توقيت القاهرة
        $leave->start_date = $leave->start_date->setTimezone('Africa/Cairo');
        $leave->end_date = $leave->end_date->setTimezone('Africa/Cairo');
        $leave->created_at = $leave->created_at->setTimezone('Africa/Cairo');
        $leave->updated_at = $leave->updated_at->setTimezone('Africa/Cairo');

        return response()->json([
            'message' => 'تم تقديم طلب الإجازة بنجاح',
            'leave' => $leave,
'deduction_amount' => $deductionAmount > 0 ? number_format($deductionAmount, 2) : null,
        ], 201);
    }

    public function approveLeave(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);

        $authUser = $request->user();
        if ($authUser->role->name === 'admin' && $leave->branch_id !== $authUser->branch_id) {
            return response()->json(['message' => 'غير مصرح لك بالموافقة على إجازة موظف من فرع آخر'], 403);
        }

        $leave->status = 'approved';
        $leave->save();

        return response()->json([
            'message' => 'تم الموافقة على الإجازة بنجاح',
            'leave' => $leave,
        ]);
    }

    public function rejectLeave(Request $request, $id)
    {
        $leave = Leave::findOrFail($id);

        $authUser = $request->user();
        if ($authUser->role->name === 'admin' && $leave->branch_id !== $authUser->branch_id) {
            return response()->json(['message' => 'غير مصرح لك برفض إجازة موظف من فرع آخر'], 403);
        }

        if ($leave->type === 'monthly') {
            $user = $leave->user;
            $user->leave_balance += $leave->days;
            $user->save();
        }

        $leave->status = 'rejected';
        $leave->save();

        return response()->json([
            'message' => 'تم رفض الإجازة',
            'leave' => $leave,
        ]);
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'type' => 'nullable|in:monthly,official,casual',
        ]);

        $query = Leave::query();

        if (isset($data['start_date'])) {
            $query->where('start_date', '>=', $data['start_date']);
        }
        if (isset($data['end_date'])) {
            $query->where('end_date', '<=', $data['end_date']);
        }
        if (isset($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin') {
            $query->where('branch_id', $authUser->branch_id);
        }

        $leaves = $query->with('user')->get();

        return response()->json([
            'message' => 'تقرير الإجازات',
            'leaves' => $leaves,
            'total_days' => number_format($leaves->sum('days'), 2),
        ]);
    }
}