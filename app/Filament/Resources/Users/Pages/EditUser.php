<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Member;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add current role and permissions to form data
        $data['role'] = $this->record->roles->first()?->name ?? 'member';
        $data['member_id'] = $this->record->member?->id;
        $data['permissions'] = $this->record->permissions->pluck('name')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        // Sync roles
        $this->record->syncRoles([$this->cachedRole]);

        // Sync permissions
        $this->record->syncPermissions($this->cachedPermissions);

        // Update member linkage
        if ($this->cachedRole === 'member' && $this->cachedMemberId) {
            // Clear any previous user_id for this member
            Member::where('user_id', $this->record->id)->update(['user_id' => null]);

            // Set new member linkage
            $member = Member::find($this->cachedMemberId);
            if ($member) {
                $member->user_id = $this->record->id;
                $member->save();
            }
        } else {
            // If role is admin, clear member linkage
            Member::where('user_id', $this->record->id)->update(['user_id' => null]);
        }
    }
}
