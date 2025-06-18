<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\Meal;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Pages\Page;

class ReportPage extends Page
{
    protected string $view = 'filament.pages.report-page';

    public $dateFrom;
    public $dateTo;

    public $data = [];

    public function generateReport()
    {

        $getAllMembers = Member::all();

        //get all meal in this range

        $meals = Meal::with('mealItems')
            ->whereBetween('date', [Carbon::parse($this->dateFrom)->format('Y-m-d'), Carbon::parse($this->dateTo)->format('Y-m-d')])

            ->get();



        foreach ($meals as $meal) {
            foreach ($meal->mealItems as $mealItem) {

                $getAllMembers = $getAllMembers->map(function ($member) use ($mealItem) {
                    if ($member->id == $mealItem->member_id) {
                        $member->totalBreakfast += $mealItem->breakfast;
                        $member->totalLunch += $mealItem->lunch;
                        $member->totalDinner += $mealItem->dinner;
                        $member->totalMeal += $mealItem->breakfast + $mealItem->lunch + $mealItem->dinner;
                    }

                    return $member;
                });
            }
        }

        //get all veriable expenses in this range

        $totalVeriableExpenses = Expense::whereBetween('created_at', [Carbon::parse($this->dateFrom)->startOfDay(), Carbon::parse($this->dateTo)->endOfDay()])
            ->where('is_fixed_cost', 0)
            ->sum('amount');

        //get all fixed expenses in this range
        $totalFixedExpenses = Expense::with('effectOn')->whereBetween('created_at', [Carbon::parse($this->dateFrom)->startOfDay(), Carbon::parse($this->dateTo)->endOfDay()])
            ->where('is_fixed_cost', 1)
            ->get();

        foreach ($totalFixedExpenses as $totalFixedExpense) {
            $avarage = $totalFixedExpense->amount / ($totalFixedExpense?->effectOn?->count() ?? 1);

            foreach ($totalFixedExpense->effectOn as $effectOn) {

                $getAllMembers = $getAllMembers->map(function ($member) use ($effectOn, $avarage) {
                    if ($member->id == $effectOn->id) {
                        $member->totalFixedExpenses += $avarage;
                    }
                    return $member;
                });
            }
        }


        $this->data = [
            'members' => $getAllMembers,
            'totalVeriableExpenses' => $totalVeriableExpenses,
        ];
    }
}
