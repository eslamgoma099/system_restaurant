<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemIngredient extends Model
{
    protected $fillable = ['item_id', 'ingredient_id', 'quantity'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}