<?php

namespace App\Filament\Resources\ExpenseRequests\Pages;

use App\Filament\Resources\ExpenseRequests\ExpenseRequestResource;
use App\Models\ExpenseRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExpenseRequests extends ListRecords
{
    protected static string $resource = ExpenseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getBaseQuery(): Builder
    {
        $query = ExpenseRequest::query();

        if (auth()->user()?->hasRole('member')) {
            $query->where('member_id', auth()->user()?->member?->id);
        }

        return $query;
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn() => $this->getBaseQuery()->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => $this->getBaseQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn() => $this->getBaseQuery()->where('status', 'approved')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn() => $this->getBaseQuery()->where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}
