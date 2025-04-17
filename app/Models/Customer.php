<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'branch_id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function loyaltyPoints()
    {
        return $this->hasMany(LoyaltyPoint::class);
    }
    public function locations()
    {
        return $this->hasMany(CustomerLocation::class);
    }

}