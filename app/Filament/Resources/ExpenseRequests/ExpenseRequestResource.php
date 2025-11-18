<?php

namespace App\Filament\Resources\ExpenseRequests;

use App\Filament\Resources\ExpenseRequests\Pages\CreateExpenseRequest;
use App\Filament\Resources\ExpenseRequests\Pages\EditExpenseRequest;
use App\Filament\Resources\ExpenseRequests\Pages\ListExpenseRequests;
use App\Filament\Resources\ExpenseRequests\Schemas\ExpenseRequestForm;
use App\Filament\Resources\ExpenseRequests\Tables\ExpenseRequestsTable;
use App\Models\ExpenseRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseRequestResource extends Resource
{
    protected static ?string $model = ExpenseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Expense Requests';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ExpenseRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseRequestsTable::configure($table);
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
            'index' => ListExpenseRequests::route('/'),
            'create' => CreateExpenseRequest::route('/create'),
            'edit' => EditExpenseRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->hasRole('admin')) {
            $count = ExpenseRequest::where('status', 'pending')->count();
            return $count > 0 ? (string) $count : null;
        }
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
