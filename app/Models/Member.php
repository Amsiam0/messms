<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $guarded = ["id"];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealSchedules(): HasMany
    {
        return $this->hasMany(MealSchedule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Options for member selects: active members, plus the one already
     * stored on the record so deactivating never blanks existing data.
     */
    public static function activeOrSelected($selectedId = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('status', 'active')
            ->when($selectedId, fn (Builder $query) => $query->orWhere('id', $selectedId))
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
