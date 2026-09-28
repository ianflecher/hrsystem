<?php

namespace App\Console\Commands;

use App\Services\Attendance\PunchFileReader;
use App\Services\Attendance\PunchImporter;
use App\Services\Attendance\ZktecoPuller;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                            {--overwrite : Replace days somebody entered by hand}
                            {--full : Import every punch on the device, not only the recent ones}';

    protected $description = 'Pull attendance from the biometric scanner, or import an export from it';

    public function handle(PunchImporter $importer): int
    {
        // One pull at a time: the device serves one connection, and the
        // button and the hourly task could otherwise start together.
        if (! $this->option('file')) {
            if (Cache::has('attendance.sync.running')) {
                $this->warn('A sync is already running.');

                return self::SUCCESS;
            }
            Cache::put('attendance.sync.running', now()->toDateTimeString(), now()->addMinutes(20));
        }

        try {
            return $this->sync($importer);
        } finally {
            if (! $this->option('file')) {
                Cache::forget('attendance.sync.running');
            }
        }
    }

    private function sync(PunchImporter $importer): int
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
            if (! $this->option('file')) {
                // The HR attendance page reads this and warns until a run succeeds.
                Cache::forever('attendance.sync.last', ['ok' => false, 'at' => now()->toDateTimeString(), 'message' => $e->getMessage()]);
            }

            return self::FAILURE;
        }

        $this->info('Found '.count($punches).' punch(es).');

        // The device can only hand over its whole log, so what is cut is the
        // import: the days already written are left alone. Three days back
        // from the last good run catches punches that arrived late. A full
        // import runs whenever an employee was edited since - a newly set
        // scanner ID has history that the recent window would miss.
        $startedAt = now();
        $lastOk = Cache::get('attendance.sync.last_ok_at');
        $since = null;
        if (! $this->option('file') && ! $this->option('full') && $lastOk) {
            // A new schedule changes who was late on days already written.
            $employeesChanged = DB::table('employees')->where('updated_at', '>', $lastOk)->exists()
                || DB::table('shift_assignments')->where('updated_at', '>', $lastOk)->exists();
            if ($employeesChanged) {
                $this->info('Employees or schedules were edited since the last sync - importing everything.');
            } else {
                $since = Carbon::parse($lastOk)->subDays(3)->startOfDay();
                $all = count($punches);
                $punches = array_values(array_filter($punches, fn ($punch) => ($punch['timestamp'] ?? '') >= $since->toDateTimeString()));
                $this->info('Importing the '.count($punches)." punch(es) since {$since->format('M j')} (of {$all}). Use --full for everything.");
            }
        }

        $summary = $importer->import($punches, (bool) $this->option('overwrite'));
        if (! $this->option('file')) {
            Cache::forever('attendance.sync.last', ['ok' => true, 'at' => now()->toDateTimeString(), 'message' => null]);
            Cache::forever('attendance.sync.last_ok_at', $startedAt->toDateTimeString());
        }

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
