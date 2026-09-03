<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->required(fn ($record) => $record === null)
                    ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->revealable()
                    ->helperText(fn ($record) => $record ? 'Leave blank to keep current password' : null),
                TextInput::make('password_confirmation')
                    ->password()
                    ->same('password')
                    ->required(fn ($record) => $record === null)
                    ->dehydrated(false)
                    ->revealable()
                    ->visible(fn ($get) => filled($get('password'))),
                Select::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Admin',
                        'member' => 'Member',
                    ])
                    ->required()
                    ->default('member')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'admin') {
                            $set('member_id', null);
                        }
                    })
                    ->helperText('Admins have full access, Members can only submit requests'),
                Select::make('member_id')
                    ->label('Link to Member')
                    ->relationship('member', 'name', fn ($query) => $query->where('status', 'active'))
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('role') === 'member')
                    ->helperText('Select which member this user account belongs to'),
                Select::make('permissions')
                    ->label('Additional Permissions')
                    ->multiple()
                    ->options([
                        'manage_meals' => 'Manage Meals',
                        'manage_expenses' => 'Manage Expenses',
                        'manage_payments' => 'Manage Payments',
                        'manage_members' => 'Manage Members',
                        'view_reports' => 'View Reports',
                    ])
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('role') === 'member')
                    ->helperText('Grant specific permissions to this member'),
            ])
            ->columns(2);
    }
}
