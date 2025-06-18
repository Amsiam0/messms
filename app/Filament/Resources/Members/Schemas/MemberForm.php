<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Member Details')->schema([
                    TextInput::make('name'),
                    TextInput::make('initial_balance')
                        ->visibleOn('create')
                        ->numeric(2)
                        ->default(0)
                        ->required(),
                ])->columns('2')
            ]);
    }
}
