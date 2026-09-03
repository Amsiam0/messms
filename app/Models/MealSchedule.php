<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class MealSchedule extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'weekday' => 'integer',
        'breakfast' => 'float',
        'lunch' => 'float',
        'dinner' => 'float',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Upsert a member's weekly schedule.
     *
     * Keyed by weekday (0 = Sunday .. 6 = Saturday), each entry holding
     * breakfast, lunch and dinner quantities. Weekdays absent from $byWeekday
     * are left as they are, so a partial edit stays a partial edit.
     */
    public static function syncForMember(Member $member, array $byWeekday): void
    {
        foreach ($byWeekday as $weekday => $quantities) {
            if (! is_int($weekday) || $weekday < 0 || $weekday > 6) {
                throw new InvalidArgumentException(
                    "Weekday must be between 0 and 6, got \"{$weekday}\"."
                );
            }

            static::updateOrCreate(
                ['member_id' => $member->id, 'weekday' => $weekday],
                [
                    'breakfast' => $quantities['breakfast'] ?? 0,
                    'lunch' => $quantities['lunch'] ?? 0,
                    'dinner' => $quantities['dinner'] ?? 0,
                ],
            );
        }
    }
}
