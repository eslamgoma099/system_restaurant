<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddon extends Model
{
    protected $fillable = [
        'order_id',
        'addon_name',
        'ingredient_id',
        'addon_price',
        'quantity',
        'order_item_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
    public function orderItem()
{
    return $this->belongsTo(OrderItem::class);
}

}