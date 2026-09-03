<?php

namespace App\Filament\Resources\ExpenseRequests\Schemas;

use App\Models\Member;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExpenseRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('note')
                    ->label('Description')
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->minValue(0),
                Toggle::make('is_fixed_cost')
                    ->label('Fixed Cost')
                    ->helperText('If this is a fixed cost, it will be distributed among members.'),
                Select::make('member_id')
                    ->options(fn ($get) => Member::activeOrSelected($get('member_id')))
                    ->searchable()
                    ->required()
                    ->default(fn() => auth()->user()?->member?->id)
                    ->disabled(fn() => auth()->user()?->hasRole('member'))
                    ->dehydrated(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->visible(fn() => auth()->user()?->hasRole('admin'))
                    ->disabled(fn($record) => !$record),
                Select::make('approved_by')
                    ->relationship('approvedBy', 'name')
                    ->visible(fn() => auth()->user()?->hasRole('admin'))
                    ->disabled(),
                DateTimePicker::make('approved_at')
                    ->visible(fn() => auth()->user()?->hasRole('admin'))
                    ->disabled(),
            ]);
    }
}
