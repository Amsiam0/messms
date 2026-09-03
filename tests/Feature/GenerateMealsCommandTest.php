<?php

use App\Models\Meal;
use App\Models\Member;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Member::create([
        'name' => 'Siam', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active',
    ]);
});

it('generates today when given no options', function () {
    Carbon::setTestNow('2026-09-06 09:00:00');

    $this->artisan('meals:generate')->assertSuccessful();

    expect(Meal::whereDate('date', '2026-09-06')->exists())->toBeTrue();
});

it('generates the date given by --date', function () {
    $this->artisan('meals:generate', ['--date' => '2026-09-20'])->assertSuccessful();

    expect(Meal::whereDate('date', '2026-09-20')->exists())->toBeTrue()
        ->and(Meal::count())->toBe(1);
});

it('generates every date in a --from/--to range', function () {
    $this->artisan('meals:generate', ['--from' => '2026-09-05', '--to' => '2026-09-07'])
        ->assertSuccessful();

    expect(Meal::count())->toBe(3);
});

it('fails when --from is given without --to', function () {
    $this->artisan('meals:generate', ['--from' => '2026-09-05'])->assertFailed();

    expect(Meal::count())->toBe(0);
});

it('fails when --date is combined with a range', function () {
    $this->artisan('meals:generate', [
        '--date' => '2026-09-05', '--from' => '2026-09-05', '--to' => '2026-09-07',
    ])->assertFailed();

    expect(Meal::count())->toBe(0);
});

it('fails on an unparseable date', function () {
    $this->artisan('meals:generate', ['--date' => 'not-a-date'])->assertFailed();

    expect(Meal::count())->toBe(0);
});

it('fails when the range ends before it starts', function () {
    $this->artisan('meals:generate', ['--from' => '2026-09-07', '--to' => '2026-09-05'])
        ->assertFailed();

    expect(Meal::count())->toBe(0);
});
