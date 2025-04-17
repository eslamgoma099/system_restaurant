<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location', 'super_admin_id',
        'latitude',
        'longitude',
'max_delivery_distance',
'price_per_km'
    ];
    public function superAdmin()
{
    return $this->belongsTo(User::class, 'super_admin_id');
}

public function users()
{
    return $this->hasMany(User::class);
}
}
