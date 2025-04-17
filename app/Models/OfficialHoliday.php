<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialHoliday extends Model
{
    protected $fillable = [
        'name',
        'date',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}