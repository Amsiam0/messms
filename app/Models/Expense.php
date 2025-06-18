<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_fixed_cost' => 'boolean',
    ];

    public function effectOn()
    {
        return $this->belongsToMany(Member::class, 'effect_on', 'expense_id', 'member_id');
    }
}
