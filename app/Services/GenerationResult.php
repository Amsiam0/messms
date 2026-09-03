<?php

namespace App\Services;

readonly class GenerationResult
{
    public function __construct(
        public int $datesProcessed = 0,
        public int $mealsCreated = 0,
        public int $itemsCreated = 0,
        public int $membersAlreadyPresent = 0,
    ) {}

    public function add(self $other): self
    {
        return new self(
            $this->datesProcessed + $other->datesProcessed,
            $this->mealsCreated + $other->mealsCreated,
            $this->itemsCreated + $other->itemsCreated,
            $this->membersAlreadyPresent + $other->membersAlreadyPresent,
        );
    }

    public function summary(): string
    {
        $summary = "Processed {$this->datesProcessed} date(s): "
            . "{$this->mealsCreated} meal(s) created, "
            . "{$this->itemsCreated} member row(s) added.";

        if ($this->membersAlreadyPresent > 0) {
            $summary .= " {$this->membersAlreadyPresent} existing row(s) left untouched.";
        }

        return $summary;
    }
}
