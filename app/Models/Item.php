<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'category_id', 'price', 'cost', 'stock_quantity', 'branch_id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function inventory()
{
    return $this->hasOne(Inventory::class);
}
public function ingredients()
{
    return $this->hasMany(ItemIngredient::class);
}

public function calculateCost()
{
    return $this->ingredients->sum(function ($itemIngredient) {
        return $itemIngredient->quantity * $itemIngredient->ingredient->cost_per_unit;
    });
}
}