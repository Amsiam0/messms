<?php

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
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

it('opens the expenses list without blowing up on the member filter', function () {
    Livewire::test(ManageExpenses::class)->assertOk();
});

it('filters expenses down to the members a fixed cost affects', function () {
    $siam = Member::create(['name' => 'Siam', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);
    $roni = Member::create(['name' => 'Roni', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);

    $siamsCost = Expense::create(['note' => 'Siam gas', 'amount' => 300, 'is_fixed_cost' => true, 'date' => '2026-09-01']);
    $siamsCost->effectOn()->sync([$siam->id]);

    $ronisCost = Expense::create(['note' => 'Roni gas', 'amount' => 400, 'is_fixed_cost' => true, 'date' => '2026-09-01']);
    $ronisCost->effectOn()->sync([$roni->id]);

    Livewire::test(ManageExpenses::class)
        ->filterTable('effectOn', [$siam->id])
        ->assertCanSeeTableRecords([$siamsCost])
        ->assertCanNotSeeTableRecords([$ronisCost]);
});
