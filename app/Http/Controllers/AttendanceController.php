<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin');
    }

    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'employee_code' => 'required|exists:users,employee_code',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // التحقق من عدم وجود تسجيل حضور في نفس اليوم
        $existingAttendance = Attendance::where('employee_code', $data['employee_code'])
            ->whereDate('check_in', now()->toDateString())
            ->first();

        if ($existingAttendance) {
            return response()->json(['message' => 'لقد تم تسجيل حضورك بالفعل اليوم'], 400);
        }

        $attendance = Attendance::create([
            'employee_code' => $data['employee_code'],
            'check_in' => now(),
            'branch_id' => $data['branch_id'] ?? null,
        ]);

        // تحويل التواريخ إلى توقيت القاهرة
        $attendance->check_in = $attendance->check_in->setTimezone('Africa/Cairo');
        $attendance->created_at = $attendance->created_at->setTimezone('Africa/Cairo');
        $attendance->updated_at = $attendance->updated_at->setTimezone('Africa/Cairo');

        return response()->json([
            'message' => 'تم تسجيل الحضور بنجاح',
            'attendance' => $attendance
        ], 201);
    }

    public function checkOut(Request $request)
    {
        $data = $request->validate([
            'employee_code' => 'required|exists:users,employee_code',
        ]);

        $today = \Carbon\Carbon::today('Africa/Cairo');

        $attendance = Attendance::where('employee_code', $data['employee_code'])
            ->whereDate('check_in', $today->toDateString())
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'لا يوجد سجل حضور لتسجيل الانصراف'], 404);
        }

        $now = now();
        $attendance->check_out = $now;
        $attendance->hours_worked = $attendance->check_in->diffInMinutes($attendance->check_out) / 60;
        $attendance->save();

        // 🔹 البحث عن الشفت المفتوح المرتبط بالكاشير (الموظف) اليوم
        $user = \App\Models\User::where('employee_code', $data['employee_code'])->first();

        $shift = \App\Models\Shift::where('cashier_id', $user->id)
            ->whereDate('start_time', $today->toDateString())
            ->whereNull('end_time')
            ->first();

        if ($shift) {
            $shift->end_time = $now;

            // 🔸 حساب الوقت الإضافي إن وجد
            if ($shift->template) {
                $scheduledEnd = \Carbon\Carbon::parse($shift->start_time->format('Y-m-d') . ' ' . $shift->template->end_time);

                // لو نهاية الشفت بعد منتصف الليل
                if ($scheduledEnd->lessThan($shift->start_time)) {
                    $scheduledEnd->addDay();
                }

                if ($now->greaterThan($scheduledEnd)) {
                    $extra = $now->diffInMinutes($scheduledEnd);
                    $shift->extra_minutes = $extra;
                } else {
                    $shift->extra_minutes = 0;
                }
            }

            $extraMinutes = $shift->calculateExtraTime();
            $shift->extra_minutes = $extraMinutes;
            $shift->save();
                    }

        return response()->json([
            'message' => 'تم تسجيل الانصراف بنجاح',
            'attendance' => $attendance,
            'shift' => $shift,
        ], 200);
    }


    public function report(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'employee_code' => 'nullable|exists:users,employee_code',
        ]);

        $query = Attendance::query();

        // تحويل التواريخ المدخلة إلى UTC لأن قاعدة البيانات تخزن التواريخ بتوقيت UTC
        if (isset($data['start_date'])) {
            $startDate = \Carbon\Carbon::parse($data['start_date'], 'Africa/Cairo')->startOfDay()->setTimezone('UTC');
            $query->where('check_in', '>=', $startDate);
        }
        if (isset($data['end_date'])) {
            $endDate = \Carbon\Carbon::parse($data['end_date'], 'Africa/Cairo')->endOfDay()->setTimezone('UTC');
            $query->where('check_in', '<=', $endDate);
        }
        if (isset($data['employee_code'])) {
            $query->where('employee_code', $data['employee_code']);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin') {
            $query->where('branch_id', $authUser->branch_id);
        }

        $attendanceRecords = $query->with('user')->get();

        return response()->json([
            'message' => 'تقرير الحضور والانصراف',
            'records' => $attendanceRecords,
            'total_hours' => number_format($attendanceRecords->sum('hours_worked'), 2),
        ], 200);
    }
}