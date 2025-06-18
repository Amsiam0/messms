<?php

namespace App\Filament\Resources\Meals\Pages;

use App\Filament\Resources\Meals\MealResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeal extends CreateRecord
{
    protected static string $resource = MealResource::class;

    public function handleRecordCreation(array $data): Model
    {

        return parent::handleRecordCreation($data);
    }
}
