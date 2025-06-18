<?php

namespace App\Filament\Resources\Meals\Schemas;

use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                DatePicker::make('date')->default(now()),
                Repeater::make('meals')
                    ->relationship('mealItems')
                    ->schema([
                        Select::make('member_id')
                            ->relationship('member', 'name')
                            ->label('Member'),
                        TextInput::make('breakfast')->numeric(2)->required()->default('0'),
                        TextInput::make('lunch')->numeric(2)->required()->default('0'),
                        TextInput::make('dinner')->numeric(2)->required()->default('0'),
                    ])->columns(4)


            ])->columns(1);
    }
}
