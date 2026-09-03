<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Charges every member their share of a period's mess cost.
 *
 * Each charge becomes an 'out' payment and reduces the member's balance, the
 * same way a manually entered payment does. A period can only be settled once:
 * the settlements table carries a unique key on the date range, so a repeated
 * click cannot double-charge anyone even if two admins press it at once.
 */
class MessSettler
{
    /**
     * @param  array<int, float>  $charges  member id => amount owed
     */
    public function settle(
        CarbonInterface $from,
        CarbonInterface $to,
        array $charges,
        ?User $by = null,
    ): Settlement {
        $this->guardNotAlreadySettled($from, $to);

        return DB::transaction(function () use ($from, $to, $charges, $by) {
            // Re-check inside the transaction so a concurrent settle loses here
            // rather than duplicating payments.
            $this->guardNotAlreadySettled($from, $to);

            $settlement = Settlement::create([
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'settled_by' => $by?->id,
                'total_amount' => 0,
                'member_count' => 0,
            ]);

            $total = 0.0;
            $count = 0;
            $label = $from->format('d M Y') . ' - ' . $to->format('d M Y');

            foreach ($charges as $memberId => $amount) {
                $charge = (float) ceil($amount);

                if ($charge <= 0) {
                    continue; // Nothing owed; no payment worth recording.
                }

                $member = Member::findOrFail($memberId);

                Payment::create([
                    'note' => "Mess settlement {$label}",
                    'amount' => $charge,
                    'type' => 'out',
                    'member_id' => $member->id,
                    'settlement_id' => $settlement->id,
                ]);

                $member->decrement('balance', $charge);

                $total += $charge;
                $count++;
            }

            $settlement->update(['total_amount' => $total, 'member_count' => $count]);

            return $settlement->fresh();
        });
    }

    public function settlementFor(CarbonInterface $from, CarbonInterface $to): ?Settlement
    {
        return Settlement::whereDate('date_from', $from->toDateString())
            ->whereDate('date_to', $to->toDateString())
            ->first();
    }

    private function guardNotAlreadySettled(CarbonInterface $from, CarbonInterface $to): void
    {
        if ($existing = $this->settlementFor($from, $to)) {
            throw new PeriodAlreadySettled($existing);
        }
    }
}
