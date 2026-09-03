<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Selection-only member table: never lists deactivated members.
 */
class MemberPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->where('status', 'active'))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('balance'),
            ]);
    }
}
