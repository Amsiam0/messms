<?php

use App\Filament\Resources\Meals\Pages\EditMeal;
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

it('shows the saved member in the repeater select when editing a meal', function () {
    $member = Member::create([
        'name' => 'Aslam', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active',
    ]);
    $meal = Meal::create(['date' => '2026-09-06']);
    $meal->mealItems()->create([
        'member_id' => $member->id, 'breakfast' => 0, 'lunch' => 1, 'dinner' => 1,
    ]);

    $state = Livewire::test(EditMeal::class, ['record' => $meal->getRouteKey()])
        ->assertOk()
        ->get('data');

    $rows = collect($state['meals'] ?? []);

    expect($rows)->toHaveCount(1);
    // Filament hydrates select state as a string; compare by value.
    expect((int) $rows->first()['member_id'])->toBe($member->id);

    // The stored value is right; the question is whether the box shows a label.
    Livewire::test(EditMeal::class, ['record' => $meal->getRouteKey()])
        ->assertSee('Aslam');
});

it('offers the active members as options on a new repeater row', function () {
    Member::create(['name' => 'Aslam', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);
    Member::create(['name' => 'Roni', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);

    $component = Livewire::test(App\Filament\Resources\Meals\Pages\CreateMeal::class)
        ->assertOk();

    // Add one empty repeater row, as clicking "Add" does.
    $component->set('data.meals', [
        'row1' => ['member_id' => null, 'breakfast' => 0, 'lunch' => 0, 'dinner' => 0],
    ]);

    $component->assertSee('Aslam')->assertSee('Roni');
});

it('lists a member who is not yet in the meal (proves options render, not just the label)', function () {
    $inMeal = Member::create(['name' => 'Zebra', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);
    $notInMeal = Member::create(['name' => 'Quokka', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);

    $meal = Meal::create(['date' => '2026-09-06']);
    $meal->mealItems()->create(['member_id' => $inMeal->id, 'breakfast' => 0, 'lunch' => 1, 'dinner' => 1]);

    Livewire::test(EditMeal::class, ['record' => $meal->getRouteKey()])
        ->assertSee('Quokka');
});
