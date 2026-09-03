<?php

namespace App\Filament\Resources\ExpenseRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use App\Models\Member;

class ExpenseRequestsTable
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
                IconColumn::make('is_fixed_cost')
                    ->label('Fixed Cost')
                    ->boolean(),
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
                    ->form(function ($record) {
                        $formFields = [];

                        // Only show member selection if it's a fixed cost
                        if ($record->is_fixed_cost) {
                            $formFields[] = Select::make('affected_members')
                                ->label('Select Members Affected by this Fixed Cost')
                                ->multiple()
                                ->options(Member::active()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('The expense will be distributed among these members.');
                        }

                        // Add payment creation option (like in ExpenseResource)
                        $formFields[] = Select::make('member_id')
                            ->label('Member')
                            ->options(Member::active()->pluck('name', 'id'))
                            ->required(fn($get) => $get('make_payment'))
                            ->searchable()
                            ->visible(fn($get) => $get('make_payment'));

                        $formFields[] = Checkbox::make('make_payment')
                            ->label('Make Payment')
                            ->default(false)
                            ->live();

                        return $formFields;
                    })
                    ->modalHeading(fn($record) => $record->is_fixed_cost ? 'Approve Expense & Select Affected Members' : 'Approve Expense Request')
                    ->modalSubmitActionLabel('Approve')
                    ->action(function ($record, array $data) {
                        // Create actual expense from approved request
                        $expense = \App\Models\Expense::create([
                            'note' => $record->note,
                            'amount' => $record->amount,
                            'is_fixed_cost' => $record->is_fixed_cost,
                        ]);

                        // If fixed cost, attach affected members
                        if ($record->is_fixed_cost && isset($data['affected_members'])) {
                            $expense->effectOn()->sync($data['affected_members']);
                        }

                        // If make_payment is checked, create payment and update balance
                        if (isset($data['make_payment']) && $data['make_payment'] && isset($data['member_id'])) {
                            \App\Models\Payment::create([
                                'note' => $record->note,
                                'amount' => $record->amount,
                                'type' => 'in',
                                'member_id' => $data['member_id'],
                            ]);

                            // Update member balance
                            $member = Member::find($data['member_id']);
                            if ($member) {
                                $member->balance += $record->amount;
                                $member->save();
                            }
                        }

                        // Update request status
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        $message = 'The expense has been created.';
                        if ($record->is_fixed_cost) {
                            $message .= ' Affected members have been assigned.';
                        }
                        if (isset($data['make_payment']) && $data['make_payment']) {
                            $message .= ' Payment created and member balance updated.';
                        }

                        Notification::make()
                            ->success()
                            ->title('Expense Request Approved')
                            ->body($message)
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
                            ->title('Expense Request Rejected')
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
