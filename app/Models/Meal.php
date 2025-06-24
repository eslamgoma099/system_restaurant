<?php

// app/Models/Meal.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['name', 'branch_id'];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'meal_items')->withPivot('quantity');
    }

    public function addons()
    {
        return $this->belongsToMany(Ingredient::class, 'meal_addons')->withPivot('quantity');
    }
}