<?php

namespace App\Filament\Resources\Meals\Pages;

use App\Filament\Resources\Meals\MealResource;
use App\Services\MealGenerator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMeals extends ListRecords
{
    protected static string $resource = MealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateFromConfig')
                ->label('Generate from config')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generate meals from member schedules')
                ->modalSubmitActionLabel('Generate')
                ->schema([
                    Select::make('generate_type')
                        ->label('Generate for')
                        ->options([
                            'single' => 'Single Date',
                            'range' => 'Date Range',
                        ])
                        ->default('single')
                        ->required()
                        ->live(),
                    DatePicker::make('date')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->visible(fn($get) => $get('generate_type') === 'single'),
                    DatePicker::make('start_date')
                        ->required()
                        ->native(false)
                        ->visible(fn($get) => $get('generate_type') === 'range'),
                    DatePicker::make('end_date')
                        ->required()
                        ->native(false)
                        ->afterOrEqual('start_date')
                        ->visible(fn($get) => $get('generate_type') === 'range'),
                ])
                ->action(function (array $data, MealGenerator $generator) {
                    $result = $data['generate_type'] === 'single'
                        ? $generator->generateFor(Carbon::parse($data['date']))
                        : $generator->generateRange(
                            Carbon::parse($data['start_date']),
                            Carbon::parse($data['end_date']),
                        );

                    Notification::make()
                        ->success()
                        ->title('Meals generated')
                        ->body($result->summary())
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
