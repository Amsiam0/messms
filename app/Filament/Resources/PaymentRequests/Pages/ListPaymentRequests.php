<?php

namespace App\Filament\Resources\PaymentRequests\Pages;

use App\Filament\Resources\PaymentRequests\PaymentRequestResource;
use App\Models\PaymentRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPaymentRequests extends ListRecords
{
    protected static string $resource = PaymentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn() => PaymentRequest::count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => PaymentRequest::where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn() => PaymentRequest::where('status', 'approved')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn() => PaymentRequest::where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}
