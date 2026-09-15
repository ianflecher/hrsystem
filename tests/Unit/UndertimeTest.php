<?php

namespace Tests\Unit;

use App\Support\Tardiness;
use App\Support\Undertime;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Leaving early costs money, so the boundaries are worth pinning: five minutes
 * is free, six is an hour, fifteen is half a day - the same steps as arriving
 * late, and the same rates.
 */
class UndertimeTest extends TestCase
{
    private const SALARY = 22000.0;

    private function out(string $time): Carbon
    {
        return Carbon::parse('2026-03-10 '.$time);
    }

    public function test_leaving_on_time_or_late_is_not_undertime(): void
    {
        $this->assertSame(0, Undertime::minutesShort($this->out('17:00:00'), '17:00:00'));
        $this->assertSame(0, Undertime::minutesShort($this->out('18:30:00'), '17:00:00'),
            'staying late is not negative undertime');
    }

    public function test_the_grace_period_is_free(): void
    {
        $this->assertSame(5, Undertime::minutesShort($this->out('16:55:00'), '17:00:00'));
        $this->assertSame(0.0, Undertime::deduction(5, self::SALARY));
        $this->assertFalse(Undertime::isShort($this->out('16:55:00'), '17:00:00'));
    }

    public function test_six_to_fourteen_minutes_costs_an_hour(): void
    {
        $hour = round(Tardiness::hourlyRate(self::SALARY), 2);

        $this->assertSame($hour, Undertime::deduction(6, self::SALARY));
        $this->assertSame($hour, Undertime::deduction(14, self::SALARY));
        $this->assertSame('one hour', Undertime::describe(6));
    }

    public function test_fifteen_minutes_or_more_costs_half_a_day(): void
    {
        $half = round(Tardiness::dailyRate(self::SALARY) / 2, 2);

        $this->assertSame($half, Undertime::deduction(15, self::SALARY));
        $this->assertSame($half, Undertime::deduction(240, self::SALARY));
        $this->assertSame('half day', Undertime::describe(15));
    }

    public function test_it_cannot_be_judged_without_a_clock_out_or_a_shift_end(): void
    {
        $this->assertNull(Undertime::minutesShort(null, '17:00:00'));
        $this->assertNull(Undertime::minutesShort($this->out('16:00:00'), null));
        $this->assertSame(0.0, Undertime::deduction(null, self::SALARY),
            'a missed scan must not cost somebody money');
    }

    public function test_a_night_shift_ending_in_the_morning_is_measured_correctly(): void
    {
        // Clocked out at 05:30 on a shift that runs to 06:00.
        $this->assertSame(30, Undertime::minutesShort(Carbon::parse('2026-03-11 05:30:00'), '06:00:00'));
    }

    public function test_late_and_short_on_the_same_day_never_cost_more_than_the_day(): void
    {
        $half = round(Tardiness::dailyRate(self::SALARY) / 2, 2);
        $day = round(Tardiness::dailyRate(self::SALARY), 2);

        $this->assertSame($day, Undertime::capDay($half, $half, self::SALARY));
        $this->assertSame($day, Undertime::capDay($day, $half, self::SALARY));
        $this->assertSame(round($half + Tardiness::hourlyRate(self::SALARY), 2),
            Undertime::capDay($half, round(Tardiness::hourlyRate(self::SALARY), 2), self::SALARY),
            'below the cap, the two simply add up');
    }
}
