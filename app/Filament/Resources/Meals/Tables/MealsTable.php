<?php

namespace App\Filament\Resources\Meals\Tables;

use App\Models\Meal;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class MealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->date(),


            ])
            ->defaultSort('date', 'desc')
            ->filters([])
            ->recordActions([
                EditAction::make(),
                Action::make('copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form([
                        Radio::make('copy_type')
                            ->label('Copy To')
                            ->options([
                                'single' => 'Single Date',
                                'range' => 'Date Range',
                            ])
                            ->default('single')
                            ->live()
                            ->required(),
                        DatePicker::make('copy_date')
                            ->label('Date')
                            ->visible(fn ($get) => $get('copy_type') === 'single')
                            ->required(fn ($get) => $get('copy_type') === 'single')
                            ->native(false),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->visible(fn ($get) => $get('copy_type') === 'range')
                            ->required(fn ($get) => $get('copy_type') === 'range')
                            ->native(false),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->visible(fn ($get) => $get('copy_type') === 'range')
                            ->required(fn ($get) => $get('copy_type') === 'range')
                            ->after('start_date')
                            ->native(false),
                    ])
                    ->modalHeading('Copy Meal')
                    ->modalSubmitActionLabel('Copy')
                    ->action(function (Meal $record, array $data) {
                        $dates = [];

                        if ($data['copy_type'] === 'single') {
                            $dates[] = Carbon::parse($data['copy_date']);
                        } else {
                            $start = Carbon::parse($data['start_date']);
                            $end = Carbon::parse($data['end_date']);

                            while ($start->lte($end)) {
                                $dates[] = $start->copy();
                                $start->addDay();
                            }
                        }

                        $copiedCount = 0;
                        $skippedCount = 0;

                        foreach ($dates as $date) {
                            // Check if meal already exists for this date
                            $existingMeal = Meal::whereDate('date', $date)->first();

                            if ($existingMeal) {
                                $skippedCount++;
                                continue;
                            }

                            // Create new meal
                            $newMeal = Meal::create([
                                'date' => $date,
                            ]);

                            // Copy all meal items
                            foreach ($record->mealItems as $mealItem) {
                                $newMeal->mealItems()->create([
                                    'member_id' => $mealItem->member_id,
                                    'breakfast' => $mealItem->breakfast,
                                    'lunch' => $mealItem->lunch,
                                    'dinner' => $mealItem->dinner,
                                ]);
                            }

                            $copiedCount++;
                        }

                        $message = "Meal copied to {$copiedCount} date(s).";
                        if ($skippedCount > 0) {
                            $message .= " {$skippedCount} date(s) skipped (already exists).";
                        }

                        Notification::make()
                            ->success()
                            ->title('Meal Copied')
                            ->body($message)
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
