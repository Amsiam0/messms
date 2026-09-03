<?php

use App\Filament\Pages\ReportPage;
use App\Models\Expense;
use App\Models\Meal;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

function reportMember(string $name): Member
{
    return Member::create(['name' => $name, 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);
}

function mealFor(string $date, Member $member, float $b, float $l, float $d): void
{
    $meal = Meal::firstOrCreate(['date' => $date]);
    $meal->mealItems()->create([
        'member_id' => $member->id, 'breakfast' => $b, 'lunch' => $l, 'dinner' => $d,
    ]);
}

it('does not crash for a range containing no meals', function () {
    reportMember('Siam');
    Expense::create(['note' => 'Fish', 'amount' => 500, 'is_fixed_cost' => false, 'date' => '2026-09-10']);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->assertOk()
        ->get('data');

    expect($data['totalMeals'])->toBe(0.0)
        ->and($data['members'][0]['variableCost'])->toBe(0.0)
        ->and($data['members'][0]['totalCost'])->toBe(0.0);
});

it('splits variable cost in proportion to meals eaten', function () {
    $siam = reportMember('Siam');
    $roni = reportMember('Roni');

    mealFor('2026-09-05', $siam, 0, 1, 1); // 2 meals
    mealFor('2026-09-05', $roni, 0, 0, 1); // 1 meal

    Expense::create(['note' => 'Bazar', 'amount' => 300, 'is_fixed_cost' => false, 'date' => '2026-09-05']);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    $byName = collect($data['members'])->keyBy('name');

    expect($data['totalMeals'])->toBe(3.0)
        ->and($byName['Siam']['variableCost'])->toBe(200.0)
        ->and($byName['Roni']['variableCost'])->toBe(100.0);
});

it('divides a fixed cost only among the members it affects', function () {
    $siam = reportMember('Siam');
    $roni = reportMember('Roni');
    mealFor('2026-09-05', $siam, 0, 0, 1);

    $fixed = Expense::create(['note' => 'Gas', 'amount' => 200, 'is_fixed_cost' => true, 'date' => '2026-09-05']);
    $fixed->effectOn()->sync([$siam->id, $roni->id]);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    $byName = collect($data['members'])->keyBy('name');

    expect($byName['Siam']['fixedCost'])->toBe(100.0)
        ->and($byName['Roni']['fixedCost'])->toBe(100.0);
});

it('reports zero meals for a member who ate nothing', function () {
    $siam = reportMember('Siam');
    reportMember('Absent');
    mealFor('2026-09-05', $siam, 0, 0, 1);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    $absent = collect($data['members'])->firstWhere('name', 'Absent');

    expect($absent['meals'])->toBe(0.0)
        ->and($absent['breakfast'])->toBe(0.0)
        ->and($absent['variableCost'])->toBe(0.0);
});

it('excludes a deactivated member from the report', function () {
    $siam = reportMember('Siam');
    $gone = reportMember('Gone');
    $gone->update(['status' => 'inactive']);
    mealFor('2026-09-05', $siam, 0, 0, 1);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    expect(collect($data['members'])->pluck('name'))->not->toContain('Gone');
});

it('renders the generated report table', function () {
    $siam = reportMember('Siam');
    mealFor('2026-09-05', $siam, 0, 1, 1);
    Expense::create(['note' => 'Bazar', 'amount' => 300, 'is_fixed_cost' => false, 'date' => '2026-09-05']);

    Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->assertOk()
        ->assertSee('Siam')
        ->assertSee('Save as image')
        ->assertSee('Rate / Meal');
});

it('warns instead of dividing by zero when no meals exist', function () {
    reportMember('Siam');
    Expense::create(['note' => 'Fish', 'amount' => 500, 'is_fixed_cost' => false, 'date' => '2026-09-10']);

    Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->assertOk()
        ->assertSee('No meals were recorded in this range');
});

it('counts an expense dated on the last day of the range', function () {
    $siam = reportMember('Siam');
    mealFor('2026-09-05', $siam, 0, 0, 1);

    // Dated exactly on dateTo - the boundary a string comparison drops.
    Expense::create(['note' => 'Last day bazar', 'amount' => 500, 'is_fixed_cost' => false, 'date' => '2026-09-30']);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    expect($data['totalVariableExpenses'])->toBe(500.0);
});

it('counts a meal dated on the last day of the range', function () {
    $siam = reportMember('Siam');
    mealFor('2026-09-30', $siam, 0, 0, 1);

    $data = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')->get('data');

    expect($data['totalMeals'])->toBe(1.0);
});

it('charges every member their total cost and updates balances', function () {
    $siam = reportMember('Siam');
    $siam->update(['balance' => 1000]);
    mealFor('2026-09-05', $siam, 0, 0, 2);
    Expense::create(['note' => 'Bazar', 'amount' => 300, 'is_fixed_cost' => false, 'date' => '2026-09-05']);

    Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->call('settleReport')
        ->assertOk();

    $payment = App\Models\Payment::where('member_id', $siam->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->type)->toBe('out')
        ->and($payment->amount)->toBe(300.0)
        ->and($siam->fresh()->balance)->toBe(700.0);
});

it('will not charge the same period twice', function () {
    $siam = reportMember('Siam');
    $siam->update(['balance' => 1000]);
    mealFor('2026-09-05', $siam, 0, 0, 2);
    Expense::create(['note' => 'Bazar', 'amount' => 300, 'is_fixed_cost' => false, 'date' => '2026-09-05']);

    $page = Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->call('settleReport')
        ->call('settleReport');

    expect(App\Models\Payment::count())->toBe(1)
        ->and(App\Models\Settlement::count())->toBe(1)
        ->and($siam->fresh()->balance)->toBe(700.0);

    $page->assertSee('Settled on');
});

it('hides the charge button once a period is settled', function () {
    $siam = reportMember('Siam');
    mealFor('2026-09-05', $siam, 0, 0, 2);
    Expense::create(['note' => 'Bazar', 'amount' => 300, 'is_fixed_cost' => false, 'date' => '2026-09-05']);

    Livewire::test(ReportPage::class)
        ->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-30')
        ->call('generateReport')
        ->assertSee('Charge members')
        ->call('settleReport')
        ->assertDontSee('Charge members');
});
