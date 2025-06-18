<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\type;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['balance'] = $data['initial_balance'];
        $data['join_date'] = now()->format('Y-m-d');
        unset($data['initial_balance']);

        $member = parent::handleRecordCreation($data);

        Payment::create([
            'member_id' => $member->id,
            'type' => 'in',
            'amount' => $member->balance,
            'note' => 'Initial Balance',
        ]);

        return $member;
    }
}
