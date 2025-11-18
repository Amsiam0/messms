<?php

namespace App\Filament\Resources\PaymentRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Checkbox;

class PaymentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('note')
                    ->label('Description')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Money In',
                        'out' => 'Money Out',
                    })
                    ->searchable(),
                TextColumn::make('member.name')
                    ->sortable()
                    ->searchable()
                    ->visible(fn() => auth()->user()?->hasRole('admin')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->searchable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending' && auth()->user()?->hasRole('admin'))
                    ->form([
                        Checkbox::make('update_balance')
                            ->label('Update Member Balance')
                            ->helperText('Check this to add/subtract this amount from the member\'s balance.')
                            ->default(true)
                    ])
                    ->modalHeading('Approve Payment Request')
                    ->modalSubmitActionLabel('Approve')
                    ->action(function ($record, array $data) {
                        // Create actual payment from approved request
                        $payment = \App\Models\Payment::create([
                            'note' => $record->note,
                            'amount' => $record->amount,
                            'type' => $record->type,
                            'member_id' => $record->member_id,
                        ]);

                        // Update member balance only if checkbox is checked
                        if (isset($data['update_balance']) && $data['update_balance']) {
                            $member = $record->member;
                            if ($record->type === 'in') {
                                $member->balance += $record->amount;
                            } else {
                                $member->balance -= $record->amount;
                            }
                            $member->save();
                        }

                        // Update request status
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        $balanceMessage = (isset($data['update_balance']) && $data['update_balance'])
                            ? ' Member balance updated.'
                            : ' Member balance NOT updated.';

                        Notification::make()
                            ->success()
                            ->title('Payment Request Approved')
                            ->body('The payment has been created.' . $balanceMessage)
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'pending' && auth()->user()?->hasRole('admin'))
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->warning()
                            ->title('Payment Request Rejected')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                if (auth()->user()?->hasRole('member')) {
                    $query->where('member_id', auth()->user()?->member?->id);
                }
            })
            ->defaultSort('created_at', 'desc');
    }
}
