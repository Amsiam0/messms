<?php

namespace App\Services;

use App\Models\Settlement;
use RuntimeException;

class PeriodAlreadySettled extends RuntimeException
{
    public function __construct(public readonly Settlement $settlement)
    {
        parent::__construct(sprintf(
            'The period %s to %s was already settled on %s.',
            $settlement->date_from->toDateString(),
            $settlement->date_to->toDateString(),
            $settlement->created_at?->toDateString() ?? 'an earlier date',
        ));
    }
}
