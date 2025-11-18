<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
