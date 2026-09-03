<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'total_amount' => 'float',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
