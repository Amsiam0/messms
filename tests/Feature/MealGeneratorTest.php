<?php

use App\Models\Meal;
use App\Models\MealItem;
use App\Models\MealSchedule;
use App\Models\Member;
use App\Services\MealGenerator;
use Carbon\Carbon;

/** 2026-09-06 is a Sunday (dayOfWeek 0); 2026-09-05 a Saturday (6). */
const SUNDAY = '2026-09-06';
const SATURDAY = '2026-09-05';

function member(string $name, string $status = 'active'): Member
{
    return Member::create([
        'name' => $name,
        'balance' => 0,
        'join_date' => '2026-01-01',
        'status' => $status,
    ]);
}

function schedule(Member $m, int $weekday, float $b, float $l, float $d): MealSchedule
{
    return MealSchedule::create([
        'member_id' => $m->id,
        'weekday' => $weekday,
        'breakfast' => $b,
        'lunch' => $l,
        'dinner' => $d,
    ]);
}

function itemFor(Member $m, string $date): ?MealItem
{
    $meal = Meal::whereDate('date', $date)->first();

    return $meal?->mealItems()->where('member_id', $m->id)->first();
}

it('creates a meal item carrying that weekday\'s configured quantities', function () {
    $siam = member('Siam');
    schedule($siam, 0, 0, 1, 1); // Sunday: lunch + dinner

    (new MealGenerator)->generateFor(Carbon::parse(SUNDAY));

    $item = itemFor($siam, SUNDAY);
    expect($item)->not->toBeNull()
        ->and($item->breakfast)->toBe(0.0)
        ->and($item->lunch)->toBe(1.0)
        ->and($item->dinner)->toBe(1.0);
});

it('does not apply a schedule belonging to a different weekday', function () {
    $siam = member('Siam');
    schedule($siam, 6, 1, 1, 1); // Saturday only

    (new MealGenerator)->generateFor(Carbon::parse(SUNDAY));

    $item = itemFor($siam, SUNDAY);
    expect($item->breakfast)->toBe(0.0)
        ->and($item->lunch)->toBe(0.0)
        ->and($item->dinner)->toBe(0.0);
});

it('gives an unconfigured member an all-zero row', function () {
    $karim = member('Karim');

    (new MealGenerator)->generateFor(Carbon::parse(SUNDAY));

    $item = itemFor($karim, SUNDAY);
    expect($item)->not->toBeNull()
        ->and($item->lunch)->toBe(0.0);
});

it('creates no row for an inactive member', function () {
    $gone = member('Gone', 'inactive');
    schedule($gone, 0, 1, 1, 1);

    (new MealGenerator)->generateFor(Carbon::parse(SUNDAY));

    expect(itemFor($gone, SUNDAY))->toBeNull();
});

it('leaves a hand-edited row untouched and creates no duplicate on re-run', function () {
    $siam = member('Siam');
    schedule($siam, 0, 0, 1, 1);

    $generator = new MealGenerator;
    $generator->generateFor(Carbon::parse(SUNDAY));

    itemFor($siam, SUNDAY)->update(['lunch' => 2]);

    $generator->generateFor(Carbon::parse(SUNDAY));

    expect(Meal::whereDate('date', SUNDAY)->count())->toBe(1)
        ->and(MealItem::where('member_id', $siam->id)->count())->toBe(1)
        ->and(itemFor($siam, SUNDAY)->lunch)->toBe(2.0);
});

it('adds a row for a member created after the first run', function () {
    member('Siam');
    $generator = new MealGenerator;
    $generator->generateFor(Carbon::parse(SUNDAY));

    $late = member('Late');
    schedule($late, 0, 0, 0, 1);

    $generator->generateFor(Carbon::parse(SUNDAY));

    expect(itemFor($late, SUNDAY)?->dinner)->toBe(1.0);
});

it('covers both endpoints of a range', function () {
    member('Siam');

    (new MealGenerator)->generateRange(Carbon::parse(SATURDAY), Carbon::parse(SUNDAY));

    expect(Meal::whereDate('date', SATURDAY)->exists())->toBeTrue()
        ->and(Meal::whereDate('date', SUNDAY)->exists())->toBeTrue()
        ->and(Meal::count())->toBe(2);
});

it('rejects a range that ends before it starts', function () {
    (new MealGenerator)->generateRange(Carbon::parse(SUNDAY), Carbon::parse(SATURDAY));
})->throws(InvalidArgumentException::class);

it('reports what it created', function () {
    member('Siam');
    member('Rahim');

    $result = (new MealGenerator)->generateFor(Carbon::parse(SUNDAY));

    expect($result->datesProcessed)->toBe(1)
        ->and($result->mealsCreated)->toBe(1)
        ->and($result->itemsCreated)->toBe(2)
        ->and($result->membersAlreadyPresent)->toBe(0);
});
