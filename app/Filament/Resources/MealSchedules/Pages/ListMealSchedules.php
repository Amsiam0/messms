<?php

namespace App\Filament\Resources\MealSchedules\Pages;

use App\Filament\Resources\MealSchedules\MealScheduleResource;
use Filament\Resources\Pages\ListRecords;

class ListMealSchedules extends ListRecords
{
    protected static string $resource = MealScheduleResource::class;

    public function getTitle(): string
    {
        return 'Meal Schedule';
    }
}
