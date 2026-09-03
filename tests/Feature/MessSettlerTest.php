<?php

use App\Models\Member;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use App\Services\MessSettler;
use App\Services\PeriodAlreadySettled;
use Illuminate\Support\Carbon;

function settleMember(string $name, float $balance = 0): Member
{
    return Member::create([
        'name' => $name, 'balance' => $balance, 'join_date' => '2026-01-01', 'status' => 'active',
    ]);
}

function admin(): User
{
    return User::factory()->create();
}

it('creates one out payment per charged member', function () {
    $siam = settleMember('Siam');
    $roni = settleMember('Roni');

    (new MessSettler)->settle(
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-30'),
        [$siam->id => 7712.83, $roni->id => 7677.17],
        admin(),
    );

    expect(Payment::count())->toBe(2)
        ->and(Payment::where('member_id', $siam->id)->first()->type)->toBe('out');
});

it('rounds each charge up to whole taka', function () {
    $siam = settleMember('Siam');

    (new MessSettler)->settle(
        Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'),
        [$siam->id => 7712.83], admin(),
    );

    expect(Payment::first()->amount)->toBe(7713.0);
});

it('decrements the member balance by the charge', function () {
    $siam = settleMember('Siam', 1000);

    (new MessSettler)->settle(
        Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'),
        [$siam->id => 200.4], admin(),
    );

    expect($siam->fresh()->balance)->toBe(799.0); // 1000 - 201
});

it('skips members with nothing to pay', function () {
    $siam = settleMember('Siam');
    $absent = settleMember('Absent');

    (new MessSettler)->settle(
        Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'),
        [$siam->id => 100, $absent->id => 0], admin(),
    );

    expect(Payment::count())->toBe(1)
        ->and(Payment::where('member_id', $absent->id)->exists())->toBeFalse();
});

it('records the period, totals and who settled it', function () {
    $siam = settleMember('Siam');
    $roni = settleMember('Roni');
    $user = admin();

    $settlement = (new MessSettler)->settle(
        Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'),
        [$siam->id => 100.2, $roni->id => 50.9], $user,
    );

    expect($settlement->member_count)->toBe(2)
        ->and($settlement->total_amount)->toBe(152.0) // 101 + 51
        ->and($settlement->settled_by)->toBe($user->id)
        ->and($settlement->payments()->count())->toBe(2);
});

it('refuses to settle the same period twice', function () {
    $siam = settleMember('Siam', 1000);
    $settler = new MessSettler;

    $settler->settle(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), [$siam->id => 100], admin());

    expect(fn() => $settler->settle(
        Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), [$siam->id => 100], admin(),
    ))->toThrow(PeriodAlreadySettled::class);

    // and nothing was charged a second time
    expect(Payment::count())->toBe(1)
        ->and($siam->fresh()->balance)->toBe(900.0);
});

it('allows settling a different period', function () {
    $siam = settleMember('Siam');
    $settler = new MessSettler;

    $settler->settle(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), [$siam->id => 100], admin());
    $settler->settle(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), [$siam->id => 100], admin());

    expect(Settlement::count())->toBe(2);
});

it('charges nobody if any single charge fails', function () {
    $siam = settleMember('Siam', 1000);

    try {
        (new MessSettler)->settle(
            Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'),
            [$siam->id => 100, 999999 => 50], admin(), // 999999 does not exist
        );
    } catch (\Throwable) {
        // expected
    }

    expect(Payment::count())->toBe(0)
        ->and(Settlement::count())->toBe(0)
        ->and($siam->fresh()->balance)->toBe(1000.0);
});
