<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplyRequest extends Model
{
    protected $fillable = ['ingredient_id', 'quantity', 'branch_id', 'status', 'requested_at'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}