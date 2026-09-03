<?php

namespace App\Filament\Resources\Members\Tables;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('balance'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'active' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                Action::make('toggleStatus')
                    ->label(fn(Member $record) => $record->isActive() ? 'Deactivate' : 'Activate')
                    ->icon(fn(Member $record) => $record->isActive() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn(Member $record) => $record->isActive() ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalDescription(fn(Member $record) => $record->isActive()
                        ? 'Deactivated members are hidden everywhere except this member list. Existing records are kept.'
                        : 'This member will become selectable again across the system.')
                    ->action(function (Member $record) {
                        $record->update([
                            'status' => $record->isActive() ? 'inactive' : 'active',
                        ]);

                        Notification::make()
                            ->title("{$record->name} is now {$record->status}")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
