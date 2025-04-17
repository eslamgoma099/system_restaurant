<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Shift extends Model
{
    protected $fillable = [
        'cashier_id',
        'opening_balance',
        'start_time',
        'end_time',
        'total_payments',
        'total_expenses',
        'final_balance',
        'shift_template_id', // 🔹 جديد: ربط مع قالب الشفت
        'extra_minutes',     // 🔹 جديد: لحساب الوقت الإضافي
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'opening_balance' => 'decimal:2',
        'total_payments' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'final_balance' => 'decimal:2',
        'extra_minutes' => 'integer', // 🔹 جديد: نوع عدد صحيح
    ];

    // 🔹 علاقة مع الموظف (الكاشير)
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    // 🔹 علاقة مع الشفتات الثابتة
    public function template()
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'cashier_id', 'cashier_id');
    }

    public function getPaymentsForShift()
    {
        $query = $this->payments()
                      ->where('payment_date', '>=', $this->start_time);

        if ($this->end_time) {
            $query->where('payment_date', '<=', $this->end_time);
        } else {
            $query->where('payment_date', '<=', now());
        }

        return $query;
    }

    // 🔹 تحديث الرصيد النهائي للشفت
    public function calculateFinalBalance()
    {
        $totalPayments = $this->getPaymentsForShift()->sum('amount');
        $totalExpenses = $this->expenses()->sum('amount');

        $this->update([
            'total_payments' => $totalPayments,
            'total_expenses' => $totalExpenses,
            'final_balance' => $this->opening_balance + $totalPayments - $totalExpenses,
        ]);

        return $this->final_balance;
    }

    // 🔹 حساب الوقت الإضافي عند تسجيل الخروج
    public function calculateExtraMinutes()
    {
        if (!$this->end_time || !$this->template) {
            return 0;
        }

        $scheduledEnd = Carbon::parse($this->start_time->format('Y-m-d') . ' ' . $this->template->end_time);

        // التعامل مع حالة عبور منتصف الليل
        if ($scheduledEnd->lessThan($this->start_time)) {
            $scheduledEnd->addDay();
        }

        return $this->end_time->greaterThan($scheduledEnd)
            ? $this->end_time->diffInMinutes($scheduledEnd)
            : 0;
    }

    public function calculateExtraTime()
    {
        if (!$this->end_time || !$this->template) {
            return 0;
        }

        $actualDuration = $this->start_time->diffInMinutes($this->end_time);
        $expectedDuration = $this->template->duration_minutes;

        return max(0, $actualDuration - $expectedDuration); // الوقت الإضافي بالدقائق
    }
}
