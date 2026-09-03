<?php

use App\Models\MealSchedule;
use App\Models\Member;

function syncMember(string $name = 'Siam'): Member
{
    return Member::create([
        'name' => $name, 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active',
    ]);
}

it('writes one row per weekday given', function () {
    $m = syncMember();

    MealSchedule::syncForMember($m, [
        0 => ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
        6 => ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
    ]);

    expect($m->mealSchedules()->count())->toBe(2)
        ->and($m->mealSchedules()->where('weekday', 0)->first()->lunch)->toBe(1.0);
});

it('updates an existing weekday rather than duplicating it', function () {
    $m = syncMember();

    MealSchedule::syncForMember($m, [0 => ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1]]);
    MealSchedule::syncForMember($m, [0 => ['breakfast' => 0, 'lunch' => 2, 'dinner' => 0]]);

    expect($m->mealSchedules()->count())->toBe(1)
        ->and($m->mealSchedules()->first()->lunch)->toBe(2.0)
        ->and($m->mealSchedules()->first()->dinner)->toBe(0.0);
});

it('keeps one member\'s schedule out of another\'s', function () {
    $a = syncMember('A');
    $b = syncMember('B');

    MealSchedule::syncForMember($a, [0 => ['breakfast' => 1, 'lunch' => 1, 'dinner' => 1]]);

    expect($b->mealSchedules()->count())->toBe(0);
});

it('accepts half meals', function () {
    $m = syncMember();

    MealSchedule::syncForMember($m, [0 => ['breakfast' => 0.5, 'lunch' => 0, 'dinner' => 0]]);

    expect($m->mealSchedules()->first()->breakfast)->toBe(0.5);
});

it('rejects a weekday outside 0-6', function () {
    MealSchedule::syncForMember(syncMember(), [7 => ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0]]);
})->throws(InvalidArgumentException::class);
