<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = [
        'employee_code',
        'start_date',
        'end_date',
        'duration',
        'status',
        'deduction_amount',
        'user_id',
    'days'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'duration' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
    ];
// في نموذج Leave
public function user()
{
    return $this->belongsTo(User::class);
}

    public function getStartDateAttribute($value)
    {
        return $value ? \Carbon\Carbon::parse($value)->setTimezone('Africa/Cairo') : null;
    }

    public function getEndDateAttribute($value)
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

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'employee_code', 'employee_code');
    // }
}