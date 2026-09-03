<?php

namespace App\Filament\Resources\MealSchedules;

use App\Filament\Resources\MealSchedules\Pages\ListMealSchedules;
use App\Filament\Resources\MealSchedules\Tables\MealSchedulesTable;
use App\Models\Member;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Weekly meal schedules, listed per member.
 *
 * The model is Member because a schedule only ever makes sense as a member's
 * whole week; the seven meal_schedules rows are edited together in one modal.
 */
class MealScheduleResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Meal Schedule';

    protected static ?string $modelLabel = 'meal schedule';

    protected static ?string $slug = 'meal-schedules';

    public static function table(Table $table): Table
    {
        return MealSchedulesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->active()->with('mealSchedules')->orderBy('name');

        if (! static::seesEveryMember()) {
            $query->where('id', auth()->user()?->member?->id);
        }

        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::seesEveryMember() || auth()->user()?->member !== null;
    }

    /** Admins and meal managers see everyone; a plain member sees only themselves. */
    protected static function seesEveryMember(): bool
    {
        $user = auth()->user();

        // can() rather than hasPermissionTo(): the latter throws
        // PermissionDoesNotExist when the permission has not been seeded.
        return (bool) ($user?->hasRole('admin') || $user?->can('manage_meals'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMealSchedules::route('/'),
        ];
    }
}
