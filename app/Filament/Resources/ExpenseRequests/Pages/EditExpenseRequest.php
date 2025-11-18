<?php

namespace App\Filament\Resources\ExpenseRequests\Pages;

use App\Filament\Resources\ExpenseRequests\ExpenseRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseRequest extends EditRecord
{
    protected static string $resource = ExpenseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
