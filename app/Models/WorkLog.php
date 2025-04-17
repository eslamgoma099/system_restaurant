<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    protected $fillable = ['user_id', 'login_time', 'logout_time', 'hours_worked'];

    protected $casts = [
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'hours_worked' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}