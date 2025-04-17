<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'table_number', 'customer_name', 'branch_id', 'reservation_time', 'status'
    ];

    protected $casts = [
        'reservation_time' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}