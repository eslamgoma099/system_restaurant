<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    protected $fillable = [
        'employee_code',
        'absence_date',
        'is_official_holiday',
    ];

    protected $casts = [
        'absence_date' => 'date',
        'is_official_holiday' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_code', 'employee_code');
    }
}