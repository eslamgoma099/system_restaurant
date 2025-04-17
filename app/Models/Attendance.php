<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_code',
        'check_in',
        'check_out',
        'hours_worked',
        'branch_id',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'hours_worked' => 'decimal:2',
    ];

    // تحويل التواريخ إلى توقيت القاهرة عند استرجاعها
    public function getCheckInAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->setTimezone('Africa/Cairo') : null;
    }

    public function getCheckOutAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->setTimezone('Africa/Cairo') : null;
    }

    public function getCreatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->setTimezone('Africa/Cairo') : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->setTimezone('Africa/Cairo') : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_code', 'employee_code');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}