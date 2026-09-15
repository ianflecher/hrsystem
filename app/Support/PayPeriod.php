<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * One semi-monthly cutoff: the 1st to the 15th, or the 16th to the end of the
 * month.
 *
 * Payroll used to run monthly, so a period was just "2026-09" and the dates
 * were derived from it. Two payslips a month means the month alone no longer
 * identifies a period, and the second half is 13 to 16 days depending on the
 * month, so the end date has to be worked out rather than assumed.
 */
class PayPeriod
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly bool $isSecondCutoff,
    ) {
    }

    /**
     * Builds a period from its start date, which is how it is stored.
     */
    public static function fromStart(string $start): self
    {
        $date = Carbon::parse($start);
        $second = $date->day >= 16;

        return new self(
            $date->copy()->day($second ? 16 : 1)->toDateString(),
            $second ? $date->copy()->endOfMonth()->toDateString()
                    : $date->copy()->day(15)->toDateString(),
            $second,
        );
    }

    public function label(): string
    {
        $start = Carbon::parse($this->start);
        $end = Carbon::parse($this->end);

        return $start->format('M j').'-'.$end->format('j').', '.$start->format('Y');
    }

    /**
     * The most recent cutoffs, newest first, for a period picker.
     *
     * @return array<int, self>
     */
    public static function recent(int $count = 12): array
    {
        $periods = [];
        $cursor = Carbon::now();

        // Start from whichever cutoff today falls in and walk backwards.
        $cursor = $cursor->day >= 16
            ? $cursor->copy()->day(16)
            : $cursor->copy()->day(1);

        for ($i = 0; $i < $count; $i++) {
            $periods[] = self::fromStart($cursor->toDateString());

            $cursor = $cursor->day >= 16
                ? $cursor->copy()->day(1)
                : $cursor->copy()->subMonthNoOverflow()->day(16);
        }

        return $periods;
    }
}
