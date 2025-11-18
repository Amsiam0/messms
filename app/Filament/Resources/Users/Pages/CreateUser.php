<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Member;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store role, permissions, and member_id for later use, remove from data
        $this->cachedRole = $data['role'] ?? 'member';
        $this->cachedMemberId = $data['member_id'] ?? null;
        $this->cachedPermissions = $data['permissions'] ?? [];
        unset($data['role']);
        unset($data['member_id']);
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Assign role to user
        $this->record->assignRole($this->cachedRole);

        // Assign permissions to user
        if (!empty($this->cachedPermissions)) {
            $this->record->givePermissionTo($this->cachedPermissions);
        }

        // Link to member if role is member and member_id is provided
        if ($this->cachedRole === 'member' && $this->cachedMemberId) {
            $member = Member::find($this->cachedMemberId);
            if ($member) {
                $member->user_id = $this->record->id;
                $member->save();
            }
        }
    }
}
