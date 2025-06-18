<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Expense;
use App\Models\Member;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ModalTableSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('effectOn')->orderBy('created_at', 'desc');
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('note')
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),

                ModalTableSelect::make('effect_on')
                    ->multiple()
                    ->relationship('effectOn', 'name')
                    ->tableConfiguration(MembersTable::class)
                    ->visible(fn($get) => $get('is_fixed_cost')),
                Checkbox::make('is_fixed_cost')->default(false)->live(),

                Select::make('member_id')
                    ->options(Member::all()->pluck('name', 'id'))
                    ->required(fn($get) => $get('make_payment'))
                    ->searchable()
                    ->visible(fn($get) => $get('make_payment')),
                Checkbox::make('make_payment')->default(false)->live(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('note')->searchable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Sum::make()->prefix('৳')
                    ]),

                IconColumn::make('is_fixed_cost'),
                TextColumn::make('effectOn.name'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('member')->multiple()->relationship('member', 'name'),

                SelectFilter::make('is_fixed_cost')->label('Fixed Cost')->options([
                    '0' => 'No',
                    '1' => 'Yes',
                ]),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')->default(Carbon::now()->firstOfMonth()),
                        DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->whereBetween('created_at', [
                                Carbon::parse($data['from'])->startOfDay(),
                                Carbon::parse($data['to'])->endOfDay(),
                            ]);
                    })
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}
