<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->databaseTransaction(true)
                ->using(function (array $data, string $model) {

                    if ($data['type'] == 'in') {
                        Member::find($data['member_id'])->increment('balance', $data['amount']);
                    } else {
                        Member::find($data['member_id'])->decrement('balance', $data['amount']);
                    }
                    return $model::create($data);
                }),
        ];
    }
}
