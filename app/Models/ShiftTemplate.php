<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time'];

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
