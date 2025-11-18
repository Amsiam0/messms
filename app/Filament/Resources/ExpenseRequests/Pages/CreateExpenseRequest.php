<?php

namespace App\Filament\Resources\ExpenseRequests\Pages;

use App\Filament\Resources\ExpenseRequests\ExpenseRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseRequest extends CreateRecord
{
    protected static string $resource = ExpenseRequestResource::class;
}
