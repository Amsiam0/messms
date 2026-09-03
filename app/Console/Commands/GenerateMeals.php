<?php

namespace App\Console\Commands;

use App\Services\MealGenerator;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use InvalidArgumentException;

class GenerateMeals extends Command
{
    protected $signature = 'meals:generate
                            {--date= : Generate this single date (defaults to today)}
                            {--from= : First date of a range, used with --to}
                            {--to= : Last date of a range, used with --from}';

    protected $description = "Create meal sheets from each member's weekly schedule";

    public function handle(MealGenerator $generator): int
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date && ($from || $to)) {
            $this->error('Use either --date or --from/--to, not both.');

            return self::FAILURE;
        }

        if (($from && ! $to) || ($to && ! $from)) {
            $this->error('--from and --to must be given together.');

            return self::FAILURE;
        }

        try {
            $result = $from
                ? $generator->generateRange($this->parse($from, '--from'), $this->parse($to, '--to'))
                : $generator->generateFor($date ? $this->parse($date, '--date') : Carbon::today());
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($result->summary());

        return self::SUCCESS;
    }

    private function parse(string $value, string $option): Carbon
    {
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (InvalidFormatException) {
            throw new InvalidArgumentException("Could not read {$option}=\"{$value}\" as a date.");
        }
    }
}
