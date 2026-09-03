<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealSchedule;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Materialises a date's meal sheet from each member's weekly schedule.
 *
 * Generation only ever fills gaps: an existing meal item is never read for its
 * values and never written to, so hand corrections survive any number of runs.
 */
class MealGenerator
{
    public function generateFor(CarbonInterface $date): GenerationResult
    {
        return DB::transaction(function () use ($date) {
            $meal = Meal::whereDate('date', $date)->first();
            $mealsCreated = 0;

            if (! $meal) {
                $meal = Meal::create(['date' => $date->toDateString()]);
                $mealsCreated = 1;
            }

            $alreadyPresent = $meal->mealItems()->pluck('member_id');

            $members = Member::active()
                ->whereNotIn('id', $alreadyPresent)
                ->get();

            $schedules = MealSchedule::where('weekday', $date->dayOfWeek)
                ->whereIn('member_id', $members->pluck('id'))
                ->get()
                ->keyBy('member_id');

            foreach ($members as $member) {
                $schedule = $schedules->get($member->id);

                $meal->mealItems()->create([
                    'member_id' => $member->id,
                    'breakfast' => $schedule->breakfast ?? 0,
                    'lunch' => $schedule->lunch ?? 0,
                    'dinner' => $schedule->dinner ?? 0,
                ]);
            }

            return new GenerationResult(
                datesProcessed: 1,
                mealsCreated: $mealsCreated,
                itemsCreated: $members->count(),
                membersAlreadyPresent: $alreadyPresent->count(),
            );
        });
    }

    public function generateRange(CarbonInterface $from, CarbonInterface $to): GenerationResult
    {
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException(
                "Range start {$from->toDateString()} is after its end {$to->toDateString()}."
            );
        }

        $result = new GenerationResult;
        $date = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($date->lessThanOrEqualTo($end)) {
            $result = $result->add($this->generateFor($date));
            $date = $date->addDay();
        }

        return $result;
    }
}
