<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';
        protected $fillable = ['item_id', 'quantity', 'branch_id', 'last_updated','ingredient_id'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}