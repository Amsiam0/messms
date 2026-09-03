<?php

namespace App\Filament\Resources\MealSchedules\Tables;

use App\Models\MealSchedule;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MealSchedulesTable
{
    /** Carbon's dayOfWeek order: 0 = Sunday .. 6 = Saturday. */
    public const WEEKDAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('schedule_summary')
                    ->label('Weekly schedule')
                    ->state(fn(Member $record) => static::summarise($record))
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('editSchedule')
                    ->label('Edit schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->modalWidth('3xl')
                    ->modalHeading(fn(Member $record) => "Meal schedule for {$record->name}")
                    ->modalSubmitActionLabel('Save schedule')
                    ->fillForm(fn(Member $record) => static::currentValues($record))
                    ->schema(static::weekdayFields())
                    ->action(function (Member $record, array $data) {
                        MealSchedule::syncForMember($record, static::toWeekdayRows($data));

                        Notification::make()
                            ->success()
                            ->title('Schedule saved')
                            ->body("Daily meals for {$record->name} will use these amounts.")
                            ->send();
                    }),
            ]);
    }

    /** @return array<int, Section> */
    protected static function weekdayFields(): array
    {
        return collect(static::WEEKDAYS)
            ->map(fn(string $label, int $weekday) => Section::make($label)
                ->compact()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make("day_{$weekday}_breakfast")->label('Breakfast')
                            ->numeric()->minValue(0)->step(0.5)->default(0)->required(),
                        TextInput::make("day_{$weekday}_lunch")->label('Lunch')
                            ->numeric()->minValue(0)->step(0.5)->default(0)->required(),
                        TextInput::make("day_{$weekday}_dinner")->label('Dinner')
                            ->numeric()->minValue(0)->step(0.5)->default(0)->required(),
                    ]),
                ]))
            ->values()
            ->all();
    }

    /** Existing rows as flat form state; absent weekdays default to zero. */
    protected static function currentValues(Member $member): array
    {
        $schedules = $member->mealSchedules->keyBy('weekday');
        $values = [];

        foreach (array_keys(static::WEEKDAYS) as $weekday) {
            $schedule = $schedules->get($weekday);

            foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
                $values["day_{$weekday}_{$meal}"] = $schedule?->{$meal} ?? 0;
            }
        }

        return $values;
    }

    /** Flat form state back into the shape syncForMember expects. */
    protected static function toWeekdayRows(array $data): array
    {
        $rows = [];

        foreach (array_keys(static::WEEKDAYS) as $weekday) {
            $rows[$weekday] = [
                'breakfast' => (float) ($data["day_{$weekday}_breakfast"] ?? 0),
                'lunch' => (float) ($data["day_{$weekday}_lunch"] ?? 0),
                'dinner' => (float) ($data["day_{$weekday}_dinner"] ?? 0),
            ];
        }

        return $rows;
    }

    /** e.g. "Lunch: Sat, Sun · Dinner: daily" */
    public static function summarise(Member $member): string
    {
        $schedules = $member->mealSchedules;

        if ($schedules->isEmpty()) {
            return 'Not configured';
        }

        $parts = [];

        foreach (['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner'] as $meal => $label) {
            $days = $schedules->filter(fn(MealSchedule $s) => $s->{$meal} > 0);

            if ($days->isEmpty()) {
                continue;
            }

            $parts[] = $days->count() === 7
                ? "{$label}: daily"
                : $label . ': ' . $days->sortBy('weekday')
                    ->map(fn(MealSchedule $s) => substr(static::WEEKDAYS[$s->weekday], 0, 3))
                    ->implode(', ');
        }

        return $parts ? implode(' · ', $parts) : 'No meals scheduled';
    }
}
