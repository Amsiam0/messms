<?php

namespace App\Filament\Resources\PaymentRequests;

use App\Filament\Resources\PaymentRequests\Pages\CreatePaymentRequest;
use App\Filament\Resources\PaymentRequests\Pages\EditPaymentRequest;
use App\Filament\Resources\PaymentRequests\Pages\ListPaymentRequests;
use App\Filament\Resources\PaymentRequests\Schemas\PaymentRequestForm;
use App\Filament\Resources\PaymentRequests\Tables\PaymentRequestsTable;
use App\Models\PaymentRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentRequestResource extends Resource
{
    protected static ?string $model = PaymentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Payment Requests';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PaymentRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentRequests::route('/'),
            'create' => CreatePaymentRequest::route('/create'),
            'edit' => EditPaymentRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->hasRole('admin')) {
            $count = PaymentRequest::where('status', 'pending')->count();
            return $count > 0 ? (string) $count : null;
        }
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
