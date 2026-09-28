<?php

namespace Tests\Feature;

use App\Services\Attendance\ZktecoDevice;
use Tests\TestCase;

/**
 * Reading the attendance log off the MB560-VL.
 *
 * rats/zkteco reads 40-byte records at fixed offsets; this device writes
 * 49-byte records. The library read every timestamp from the wrong bytes and
 * produced dates from 2000 to 2133, with a different total on every pull.
 *
 * The fixture is real: the header and last sixty records of an actual pull
 * from this device, which contain two punches independently confirmed in
 * ZKTime. If the layout is ever misread again, those two stop matching.
 */
class ZktecoAttendanceDecodeTest extends TestCase
{
    private function punches(): array
    {
        return ZktecoDevice::decodeLog(
            file_get_contents(base_path('tests/Fixtures/zkteco-mb560-attendance.bin'))
        );
    }

    /** Confirmed in ZKTime, which reads this device correctly. */
    public function test_known_punches_decode_to_the_right_person_and_time(): void
    {
        $punches = array_map(fn ($p) => ["biometric_id" => $p["biometric_id"], "timestamp" => $p["timestamp"]], $this->punches());

        $this->assertContains(
            ['biometric_id' => '197', 'timestamp' => '2026-09-27 08:55:28'],
            $punches,
            "Pintang's Sunday punch did not decode - the record layout is being misread",
        );

        $this->assertContains(
            ['biometric_id' => '294', 'timestamp' => '2026-09-27 10:58:26'],
            $punches,
            "Alcazar's Sunday punch did not decode",
        );
    }

    /** The symptom of the old bug: dates from 2000 to 2133. */
    public function test_every_date_is_one_somebody_could_have_punched(): void
    {
        foreach ($this->punches() as $punch) {
            $year = (int) substr($punch['timestamp'], 0, 4);

            $this->assertTrue($year >= 2015 && $year <= (int) date('Y') + 1,
                "a punch decoded to {$punch['timestamp']} - the timestamp is being read from the wrong bytes");
        }
    }

    public function test_every_record_is_read(): void
    {
        $this->assertCount(60, $this->punches(), 'records were skipped or split');
    }

    public function test_badges_are_clean_numbers(): void
    {
        foreach ($this->punches() as $punch) {
            $this->assertMatchesRegularExpression('/^\d+$/', $punch['biometric_id'],
                'a badge carried padding or binary from the wrong offset');
        }
    }

    /**
     * A format it does not recognise is refused, not guessed at. Importing
     * wrong dates is worse than importing nothing: it looks like attendance.
     */
    public function test_an_unrecognised_format_is_refused_rather_than_guessed(): void
    {
        $this->expectException(\RuntimeException::class);

        ZktecoDevice::decodeLog(str_repeat("\xFF", 12 + 49 * 20));
    }

    /** The key pressed on the device comes through with every punch. */
    public function test_every_punch_carries_the_key_that_was_pressed(): void
    {
        foreach ($this->punches() as $punch) {
            $this->assertArrayHasKey("state", $punch);
            $this->assertContains($punch["state"], [0, 1, 2, 3, 4, 5], "a punch carried state {$punch["state"]}");
        }
    }
}
