<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'start_date',
        'end_date',
        'notes',
        'branch_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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
}