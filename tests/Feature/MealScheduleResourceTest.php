<?php

use App\Filament\Resources\MealSchedules\MealScheduleResource;
use App\Filament\Resources\MealSchedules\Pages\ListMealSchedules;
use App\Models\MealSchedule;
use App\Models\Member;
use App\Models\User;
use Spatie\Permission\Models\Role;

use Livewire\Livewire;

function makeMember(string $name, string $status = 'active'): Member
{
    return Member::create([
        'name' => $name, 'balance' => 0, 'join_date' => '2026-01-01', 'status' => $status,
    ]);
}

function actingAsRole(string $role, ?Member $member = null): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $member?->update(['user_id' => $user->id]);

    test()->actingAs($user);

    return $user;
}

it('shows every active member to an admin', function () {
    $a = makeMember('Aslam');
    $b = makeMember('Rahim');
    actingAsRole('admin');

    Livewire::test(ListMealSchedules::class)->assertCanSeeTableRecords([$a, $b]);
});

it('hides deactivated members', function () {
    $active = makeMember('Active');
    $gone = makeMember('Gone', 'inactive');
    actingAsRole('admin');

    Livewire::test(ListMealSchedules::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$gone]);
});

it('shows a member only their own row', function () {
    $mine = makeMember('Mine');
    $theirs = makeMember('Theirs');
    actingAsRole('member', $mine);

    Livewire::test(ListMealSchedules::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('denies access to a user with no linked member', function () {
    actingAsRole('member');

    expect(MealScheduleResource::canViewAny())->toBeFalse();
});

it('saves a schedule through the edit action', function () {
    $member = makeMember('Aslam');
    actingAsRole('admin');

    $data = [];
    foreach (range(0, 6) as $day) {
        $data["day_{$day}_breakfast"] = 0;
        $data["day_{$day}_lunch"] = 0;
        $data["day_{$day}_dinner"] = 1;
    }
    $data['day_0_lunch'] = 1;   // Sunday lunch
    $data['day_6_lunch'] = 0.5; // Saturday half lunch

    Livewire::test(ListMealSchedules::class)
        ->callTableAction('editSchedule', $member, $data)
        ->assertHasNoTableActionErrors();

    $schedules = $member->fresh()->mealSchedules->keyBy('weekday');
    expect($schedules)->toHaveCount(7)
        ->and($schedules[0]->lunch)->toBe(1.0)
        ->and($schedules[6]->lunch)->toBe(0.5)
        ->and($schedules[3]->dinner)->toBe(1.0)
        ->and($schedules[3]->lunch)->toBe(0.0);
});

it('summarises a configured week', function () {
    $member = makeMember('Aslam');
    foreach (range(0, 6) as $day) {
        MealSchedule::create([
            'member_id' => $member->id, 'weekday' => $day,
            'breakfast' => 0, 'lunch' => in_array($day, [0, 6]) ? 1 : 0, 'dinner' => 1,
        ]);
    }

    $summary = App\Filament\Resources\MealSchedules\Tables\MealSchedulesTable::summarise($member->fresh());

    expect($summary)->toBe('Lunch: Sun, Sat · Dinner: daily');
});

it('summarises an unconfigured member', function () {
    expect(App\Filament\Resources\MealSchedules\Tables\MealSchedulesTable::summarise(makeMember('New')))
        ->toBe('Not configured');
});
