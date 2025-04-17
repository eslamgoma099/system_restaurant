<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'branch_id',
        'hourly_rate',
        'work_hours',
        'employee_code',
        'leave_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hourly_rate' => 'decimal:2',
        'work_hours' => 'decimal:2',
        'leave_balance' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'cashier_id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'employee_code', 'employee_code');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function advances()
    {
        return $this->hasMany(Expense::class)->where('type', 'advance');
    }

    public function calculateTotalSalary()
    {
        return $this->workLogs->sum(function ($log) {
            return $log->hours_worked * $this->hourly_rate;
        });
    }

    public function calculateSalary($startDate, $endDate, $deductibleLeaveDays = 0)
    {
        // استرجاع سجلات الحضور في الفترة
        $attendances = Attendance::where('employee_code', $this->employee_code)
            ->whereBetween('check_in', [$startDate, $endDate])
            ->get();

        // حساب إجمالي ساعات العمل
        $totalHoursWorked = $attendances->sum('hours_worked');

        // حساب الراتب الأساسي بناءً على ساعات العمل
        $baseSalary = $totalHoursWorked * $this->hourly_rate;

        // استرجاع الإجازات للحصول على أي خصومات إضافية (deduction_amount)
        $leaves = Leave::where('employee_code', $this->employee_code)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        $leaveDeduction = $leaves->sum('deduction_amount');

        // خصم الأيام الزائدة من الإجازات (إذا كانت أكثر من 4 أيام)
        $leaveDaysDeduction = $this->calculateDeduction($deductibleLeaveDays);

        // حساب الراتب النهائي
        $netSalary = $baseSalary - $leaveDeduction - $leaveDaysDeduction;

        return [
            'total_hours_worked' => number_format($totalHoursWorked, 2),
            'base_salary' => number_format($baseSalary, 2),
            'leave_deduction' => number_format($leaveDeduction, 2),
            'leave_days_deduction' => number_format($leaveDaysDeduction, 2),
            'net_salary' => max(0, number_format($netSalary, 2)), // التأكد من أن الراتب لا يكون سالبًا
        ];
    }
    public function salaryPayments()
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function resetMonthlyLeaveBalance()
    {
        $usedLeaves = $this->leaves()
            ->where('type', 'monthly')
            ->whereMonth('start_date', Carbon::now()->month)
            ->sum('days');

        $remainingLeaves = max(0, 4 - $usedLeaves);
        $this->leave_balance = $remainingLeaves;
        $this->save();
    }
    public function calculateDailyRate()
    {
        return $this->hourly_rate * 8; // الراتب اليومي = الراتب بالساعة × 8 ساعات
    }

    // حساب قيمة الخصم بناءً على عدد الأيام
    public function calculateDeduction($days)
    {
        return $this->calculateDailyRate() * $days;
    }
}