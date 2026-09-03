<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\Meal;
use App\Models\Member;
use App\Services\MessSettler;
use App\Services\PeriodAlreadySettled;
use BackedEnum;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ReportPage extends Page
{
    protected string $view = 'filament.pages.report-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public $dateFrom;

    public $dateTo;

    public $data = [];

    /** Settlement covering the currently generated range, if any. */
    public $settlement = null;

    public function mount()
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
    }

    /**
     * Every figure the view shows is computed here, so the template only
     * formats what it is handed. Division by the meal total in particular has
     * to happen where the zero case can be handled.
     */
    public function generateReport()
    {
        $from = Carbon::parse($this->dateFrom)->toDateString();
        $to = Carbon::parse($this->dateTo)->toDateString();

        $members = Member::active()->orderBy('name')->get()
            ->mapWithKeys(fn(Member $member) => [$member->id => [
                'id' => $member->id,
                'name' => $member->name,
                'balance' => (float) $member->balance,
                'breakfast' => 0.0,
                'lunch' => 0.0,
                'dinner' => 0.0,
                'meals' => 0.0,
                'fixedCost' => 0.0,
                'variableCost' => 0.0,
                'totalCost' => 0.0,
            ]])
            ->all();

        foreach (Meal::with('mealItems')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->get() as $meal) {
            foreach ($meal->mealItems as $item) {
                if (! isset($members[$item->member_id])) {
                    continue; // A meal belonging to a member who is no longer active.
                }

                $members[$item->member_id]['breakfast'] += $item->breakfast;
                $members[$item->member_id]['lunch'] += $item->lunch;
                $members[$item->member_id]['dinner'] += $item->dinner;
                $members[$item->member_id]['meals'] += $item->breakfast + $item->lunch + $item->dinner;
            }
        }

        // whereDate, not whereBetween: the date cast stores '... 00:00:00', so a
        // plain Y-m-d upper bound would exclude the range's last day.
        $expenses = Expense::with('effectOn')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        $totalVariableExpenses = (float) $expenses->where('is_fixed_cost', false)->sum('amount');

        foreach ($expenses->where('is_fixed_cost', true) as $expense) {
            $affected = $expense->effectOn;

            if ($affected->isEmpty()) {
                continue; // Nobody to charge it to.
            }

            $share = $expense->amount / $affected->count();

            foreach ($affected as $member) {
                if (isset($members[$member->id])) {
                    $members[$member->id]['fixedCost'] += $share;
                }
            }
        }

        $totalMeals = array_sum(array_column($members, 'meals'));

        // No meals in range means no per-meal rate exists; everyone's share is
        // zero rather than a division by zero.
        $ratePerMeal = $totalMeals > 0 ? $totalVariableExpenses / $totalMeals : 0.0;

        foreach ($members as $id => $member) {
            $members[$id]['variableCost'] = round($member['meals'] * $ratePerMeal, 2);
            $members[$id]['totalCost'] = round($member['fixedCost'] + $members[$id]['variableCost'], 2);
        }

        $this->data = [
            'members' => array_values($members),
            'totalVariableExpenses' => $totalVariableExpenses,
            'totalFixedExpenses' => round(array_sum(array_column($members, 'fixedCost')), 2),
            'totalCost' => round(array_sum(array_column($members, 'totalCost')), 2),
            'totalMeals' => (float) $totalMeals,
            'ratePerMeal' => round($ratePerMeal, 2),
            'dateFrom' => $from,
            'dateTo' => $to,
        ];

        $this->refreshSettlement();
    }

    protected function refreshSettlement(): void
    {
        if (! $this->data) {
            $this->settlement = null;

            return;
        }

        $existing = app(MessSettler::class)->settlementFor(
            Carbon::parse($this->data['dateFrom']),
            Carbon::parse($this->data['dateTo']),
        );

        $this->settlement = $existing ? [
            'settled_on' => $existing->created_at?->format('d M Y'),
            'settled_by' => $existing->settledBy?->name,
            'total' => $existing->total_amount,
            'members' => $existing->member_count,
        ] : null;
    }

    /**
     * Charge every member their share of this period, as an 'out' payment that
     * reduces their balance. Refuses if the period was already settled.
     */
    public function settleReport(): void
    {
        if (! $this->data) {
            return;
        }

        $charges = collect($this->data['members'])
            ->mapWithKeys(fn(array $m) => [$m['id'] => $m['totalCost']])
            ->all();

        try {
            $settlement = app(MessSettler::class)->settle(
                Carbon::parse($this->data['dateFrom']),
                Carbon::parse($this->data['dateTo']),
                $charges,
                auth()->user(),
            );
        } catch (PeriodAlreadySettled $e) {
            Notification::make()
                ->danger()
                ->title('Already settled')
                ->body($e->getMessage())
                ->send();

            $this->refreshSettlement();

            return;
        }

        Notification::make()
            ->success()
            ->title('Members charged')
            ->body(sprintf(
                '%d member(s) charged a total of %s. Balances updated.',
                $settlement->member_count,
                number_format($settlement->total_amount, 2),
            ))
            ->send();

        $this->generateReport();
    }
}
