<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'cashier_id', 'amount', 'payment_method', 'payment_date', 'branch_id'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}