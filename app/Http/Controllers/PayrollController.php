<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\SalaryPayment;
use App\Models\OfficialHoliday;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Leave;
use Carbon\Carbon;
use App\Models\Absence;

class PayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin');
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $employeeRoleId = Role::where('name', 'employee')->first()->id;
        $cashierRoleId = Role::where('name', 'cashier')->first()->id;

        $query = User::whereIn('role_id', [$employeeRoleId, $cashierRoleId]);

        $authUser = $request->user();
        if ($authUser->role->name === 'admin') {
            $query->where('branch_id', $authUser->branch_id);
        }

        if (isset($data['user_id'])) {
            $query->where('id', $data['user_id']);
        }

        $employees = $query->with('role')->get();

        $payroll = $employees->map(function ($employee) use ($data) {
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;

            if ($startDate && $endDate) {
                // العثور على أول يوم حضور خلال الفترة
                $firstAttendance = Attendance::where('employee_code', $employee->employee_code)
                    ->whereBetween('check_in', [$startDate, $endDate])
                    ->orderBy('check_in', 'asc')
                    ->first();

                if ($firstAttendance) {
                    $startDate = $firstAttendance->check_in->startOfDay();
                }

                // استرجاع الإجازات الموافق عليها
                $leaves = Leave::where('employee_code', $employee->employee_code)
                    ->where('status', 'approved')
                    ->whereBetween('start_date', [$startDate, $endDate])
                    ->get();

                $totalLeaveDays = $leaves->sum('duration');
                $deductibleLeaveDays = $totalLeaveDays > 4 ? $totalLeaveDays - 4 : 0;
            } else {
                $deductibleLeaveDays = 0;
            }

            $salaryDetails = $employee->calculateSalary(
                $startDate,
                $endDate,
                $deductibleLeaveDays
            );

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role->name,
                'branch_id' => $employee->branch_id,
                'leave_balance' => number_format($employee->leave_balance, 2),
                'salary_details' => $salaryDetails,
            ];
        });


        $totals = [
            'total_base_salary' => number_format($payroll->sum('salary_details.base_salary'), 2),
            'total_advances' => number_format($payroll->sum('salary_details.total_advances'), 2),
            'total_net_salary' => number_format($payroll->sum('salary_details.net_salary'), 2),
        ];

        return response()->json([
            'message' => 'تم إنشاء تقرير الرواتب بنجاح',
            'payroll' => $payroll,
            'totals' => $totals,
        ]);
    }

    public function payEmployee(Request $request, $userId)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m', // مثال: "2025-04" لتحديد الشهر
            'notes' => 'nullable|string',
        ]);

        $employee = User::findOrFail($userId);

        $employeeRoleId = Role::where('name', 'employee')->first()->id;
        $cashierRoleId = Role::where('name', 'cashier')->first()->id;

        if (!in_array($employee->role_id, [$employeeRoleId, $cashierRoleId])) {
            return response()->json(['message' => 'يمكن دفع الراتب فقط للموظفين أو الكاشير'], 403);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin' && $employee->branch_id !== $authUser->branch_id) {
            return response()->json(['message' => 'غير مصرح لك بدفع راتب موظف من فرع آخر'], 403);
        }

        // تحديد الفترة تلقائيًا
        $monthStart = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // العثور على أول يوم عمل (أول تسجيل حضور)
        $firstAttendance = Attendance::where('employee_code', $employee->employee_code)
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->orderBy('check_in', 'asc')
            ->first();

        if (!$firstAttendance) {
            return response()->json(['message' => 'لا يوجد تسجيل حضور لهذا الموظف في هذا الشهر'], 404);
        }

        $startDate = $firstAttendance->check_in->startOfDay();
        $endDate = $monthEnd;

        // استرجاع الإجازات الموافق عليها في الفترة
        $leaves = Leave::where('employee_code', $employee->employee_code)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        $totalLeaveDays = $leaves->sum('duration');
        $deductibleLeaveDays = $totalLeaveDays > 4 ? $totalLeaveDays - 4 : 0;

        // حساب الراتب
        $salaryDetails = $employee->calculateSalary($startDate->toDateString(), $endDate->toDateString(), $deductibleLeaveDays);

        // إنشاء سجل الدفع
        $payment = SalaryPayment::create([
            'user_id' => $employee->id,
            'amount' => str_replace(',', '', $salaryDetails['net_salary']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $data['notes'] ?? null,
            'branch_id' => $employee->branch_id,
        ]);

        return response()->json([
            'message' => 'تم دفع الراتب بنجاح',
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role->name,
            ],
            'salary_details' => $salaryDetails,
            'payment' => $payment,
        ]);
    }

    public function updateHourlyRate(Request $request, $id)
    {
        $data = $request->validate([
            'hourly_rate' => 'required|numeric|min:0',
        ]);

        $user = User::findOrFail($id);

        $employeeRoleId = Role::where('name', 'employee')->first()->id;
        $cashierRoleId = Role::where('name', 'cashier')->first()->id;

        if (!in_array($user->role_id, [$employeeRoleId, $cashierRoleId])) {
            return response()->json(['message' => 'يمكن تحديث الأجر بالساعة فقط للموظفين أو الكاشير'], 403);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin' && $user->branch_id !== $authUser->branch_id) {
            return response()->json(['message' => 'غير مصرح لك بتحديث الأجر لموظف من فرع آخر'], 403);
        }

        $user->hourly_rate = $data['hourly_rate'];
        $user->save();

        return response()->json([
            'message' => 'تم تحديث الأجر بالساعة بنجاح',
            'user' => $user->load('role'),
        ]);
    }

    public static function resetLeaveBalances()
    {
        Log::info('Starting leave balance reset at ' . now()->setTimezone('Africa/Cairo')->toDateTimeString());

        // التحقق من وجود الأدوار
        $employeeRole = Role::where('name', 'employee')->first();
        $cashierRole = Role::where('name', 'cashier')->first();

        if (!$employeeRole || !$cashierRole) {
            Log::error('Required roles (employee or cashier) not found. Aborting leave balance reset.');
            return;
        }

        // استرجاع معرفات الأدوار
        $roleIds = [$employeeRole->id, $cashierRole->id];

        // تحديث جماعي: إذا كان leave_balance > 0، أضف 4 أيام، وإلا أعد التعيين إلى 4
        $affectedRows = User::whereIn('role_id', $roleIds)
            ->update([
                'leave_balance' => \DB::raw('CASE WHEN leave_balance > 0 THEN leave_balance + 4 ELSE 4 END'),
            ]);

        Log::info("Leave balances reset successfully. Affected rows: {$affectedRows}");
    }
    public function absenceReport(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'employee_code' => 'nullable|exists:users,employee_code',
        ]);

        // تسجيل عملية استرجاع التقرير (التحسين 1)
        \Log::info("Generating absence report for period {$data['start_date']} to {$data['end_date']}" .
                   (isset($data['employee_code']) ? " for employee {$data['employee_code']}" : ''));

        $query = Absence::query()
            ->whereBetween('absence_date', [$data['start_date'], $data['end_date']]);

        if (isset($data['employee_code'])) {
            $query->where('employee_code', $data['employee_code']);
        }

        $authUser = $request->user();
        if ($authUser->role->name === 'admin') {
            $query->whereHas('user', function ($q) use ($authUser) {
                $q->where('branch_id', $authUser->branch_id);
            });
        }

        $absences = $query->with('user')->get();

        // إضافة اسم اليوم باللغة العربية (التحسين 2)
        $absences->map(function ($absence) {
            $absence->day_name = Carbon::parse($absence->absence_date)->locale('ar')->dayName;
            return $absence;
        });

        // تسجيل نجاح استرجاع التقرير (التحسين 1)
        \Log::info("Absence report generated successfully. Total records: {$absences->count()}");

        return response()->json([
            'message' => 'تقرير الغياب',
            'records' => $absences,
            'total_absences' => $absences->count(),
        ]);
    }
}