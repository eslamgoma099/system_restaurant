<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'table_number', 'status', 'branch_id', 'employee_id', 'cashier_id',
        'total_price', 'payment_method', 'payment_status','order_type','customer_location_id', // أضفنا نوع الطلب
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    // public function payment()
    // {
    //     return $this->hasOne(Payment::class);
    // }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function addons()
    {
        return $this->hasMany(OrderAddon::class);
    }
    public function customerLocation()
    {
        return $this->belongsTo(CustomerLocation::class, 'customer_location_id');
    }
    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

}