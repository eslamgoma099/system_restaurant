<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyClosure extends Model
{
    protected $fillable = [
        'branch_id', 'cashier_id', 'total_cash', 'total_card',
        'total_revenue', 'closure_date'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}