<?php

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
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

it('does not offer a deactivated member in the affected-member filter', function () {
    Member::create(['name' => 'ActiveGuy', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'active']);
    Member::create(['name' => 'GoneGuy', 'balance' => 0, 'join_date' => '2026-01-01', 'status' => 'inactive']);

    Livewire::test(ManageExpenses::class)
        ->assertSee('ActiveGuy')
        ->assertDontSee('GoneGuy');
});
