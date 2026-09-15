<?php

namespace App\Console\Commands;

use App\Services\Attendance\PunchFileReader;
use App\Services\Attendance\PunchImporter;
use App\Services\Attendance\ZktecoPuller;
use Illuminate\Console\Command;

/**
 * Brings attendance in from the scanner, or from a file exported from it.
 *
 * Made a command as well as a screen button so it can be scheduled - a nightly
 * run means nobody has to remember, and payroll finds the days already there.
 */
class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync
                            {--file= : Read an export instead of the device}
                            {--overwrite : Replace days somebody entered by hand}';

    protected $description = 'Pull attendance from the biometric scanner, or import an export from it';

    public function handle(PunchImporter $importer): int
    {
        try {
            if ($file = $this->option('file')) {
                $this->info("Reading {$file}...");
                $result = (new PunchFileReader)->read($file);
                $punches = $result['punches'];

                if ($result['unreadable'] > 0) {
                    $this->warn("  {$result['unreadable']} row(s) could not be read and were left out.");
                }
            } else {
                $this->info('Connecting to the scanner...');
                $punches = ZktecoPuller::fromConfig()->punches();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Found '.count($punches).' punch(es).');

        $summary = $importer->import($punches, (bool) $this->option('overwrite'));

        $this->newLine();
        $this->info("Wrote {$summary['days']} day(s) across {$summary['employees']} employee(s).");

        if ($summary['unknown']) {
            $this->newLine();
            $this->warn('These enrolment numbers are not linked to anybody here, so their punches were ignored:');
            $this->line('  '.implode(', ', $summary['unknown']));
            $this->line('  Set each one on the employee at /hr/employees.');
        }

        return self::SUCCESS;
    }
}
