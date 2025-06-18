<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Member;
use App\Models\Payment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->databaseTransaction(true)
                ->using(function (array $data, string $model) {

                    if ($data['make_payment']) {
                        Payment::create([
                            'member_id' => $data['member_id'],
                            'type' => 'in',
                            'amount' => $data['amount'],
                            'note' => $data['note'],
                        ]);

                        Member::find($data['member_id'])->increment('balance', $data['amount']);
                    }
                    unset($data['member_id']);
                    unset($data['make_payment']);

                    return $model::create($data);
                }),
        ];
    }
}
