<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = ['name', 'unit', 'cost_per_unit', 'branch_id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function itemIngredients()
    {
        return $this->hasMany(ItemIngredient::class);
    }
}