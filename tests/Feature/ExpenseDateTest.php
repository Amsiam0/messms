<?php

use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\Member;
use Illuminate\Support\Carbon;

function expenseMember(string $name = 'Siam'): Member
{
    return Member::create([
        'name' => $name, 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active',
    ]);
}

it('stores the date it was given, not the date it was entered', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    $expense = Expense::create(['note' => 'Fish', 'amount' => 500, 'date' => '2026-09-01']);

    expect($expense->fresh()->date->toDateString())->toBe('2026-09-01')
        ->and($expense->fresh()->created_at->toDateString())->toBe('2026-09-10');
});

it('defaults the date to today when none is given', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');

    expect(Expense::create(['note' => 'Rice', 'amount' => 100])->fresh()->date->toDateString())
        ->toBe('2026-09-10');
});

it('counts a backdated expense in the period it was spent', function () {
    Carbon::setTestNow('2026-09-10 12:00:00');
    Expense::create(['note' => 'Fish', 'amount' => 500, 'date' => '2026-08-15']);

    $august = Expense::whereBetween('date', ['2026-08-01', '2026-08-31'])->sum('amount');
    $september = Expense::whereBetween('date', ['2026-09-01', '2026-09-30'])->sum('amount');

    expect((float) $august)->toBe(500.0)->and((float) $september)->toBe(0.0);
});

it('carries a request\'s date onto the approved expense', function () {
    $member = expenseMember();
    Carbon::setTestNow('2026-09-10 12:00:00');

    $request = ExpenseRequest::create([
        'note' => 'Fish', 'amount' => 500, 'is_fixed_cost' => false,
        'member_id' => $member->id, 'status' => 'pending', 'date' => '2026-09-01',
    ]);

    $expense = Expense::create([
        'note' => $request->note,
        'amount' => $request->amount,
        'is_fixed_cost' => $request->is_fixed_cost,
        'date' => $request->date,
    ]);

    expect($expense->fresh()->date->toDateString())->toBe('2026-09-01');
});

it('falls back to today for a request saved before dates existed', function () {
    $member = expenseMember();
    Carbon::setTestNow('2026-09-10 12:00:00');

    $request = ExpenseRequest::create([
        'note' => 'Old', 'amount' => 100, 'is_fixed_cost' => false,
        'member_id' => $member->id, 'status' => 'pending',
    ]);
    $request->update(['date' => null]);

    $expense = Expense::create([
        'note' => $request->note,
        'amount' => $request->amount,
        'date' => $request->fresh()->date ?? Carbon::today(),
    ]);

    expect($expense->fresh()->date->toDateString())->toBe('2026-09-10');
});
