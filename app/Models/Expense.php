<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_fixed_cost' => 'boolean',
        'date' => 'date',
    ];

    protected static function booted(): void
    {
        // The date an expense is recorded against defaults to the day it is
        // entered, but is always the caller's to override.
        static::creating(function (self $expense) {
            $expense->date ??= Carbon::today();
        });
    }

    public function effectOn()
    {
        return $this->belongsToMany(Member::class, 'effect_on', 'expense_id', 'member_id');
    }
}
