<?php

namespace App\Console\Commands;

use App\Services\Attendance\PunchImporter;
use App\Services\Attendance\ZktecoDevice;
use App\Services\Auditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Clears ONLY the scanner's attendance log (command 15, CMD_CLEAR_ATT_LOG), so
 * syncs stop downloading years of old punches. People and fingerprints are a
 * separate store on the device and are never touched.
 *
 * In one connection, with the keypad locked: read the whole log, keep every
 * punch in scanner_punches, check every distinct punch is kept and the people
 * list reads in full, clear, then check the log is empty and the people are all
 * still there. Any check that fails stops it before anything is cleared.
 */
class ClearScannerLog extends Command
{
    protected $signature = 'attendance:clear-log {--tries=3 : Attempts when the scanner Wi-Fi drops the download}';

    protected $description = 'Archive every punch, then clear only the attendance log on the scanner';

    public function handle(PunchImporter $importer): int
    {
        for ($try = 1; $try <= max(1, (int) $this->option('tries')); $try++) {
            if (SyncAttendance::isRunning()) {
                $this->warn("Attempt {$try}: a sync is running - waiting a minute.");
                sleep(60);
                continue;
            }
            $this->info("Attempt {$try} at ".now()->format('g:i:s A'));
            try {
                if ($this->attempt($importer)) {
                    return self::SUCCESS;
                }

                return self::FAILURE; // a safety check said no - not a Wi-Fi problem, so no retry
            } catch (\Throwable $e) {
                $this->error('  '.$e->getMessage());
                if ($try < (int) $this->option('tries')) sleep(90);
            }
        }
        $this->error('Not cleared - the scanner did not send its log. Nothing was lost.');

        return self::FAILURE;
    }

    private function attempt(PunchImporter $importer): bool
    {
        Cache::put('attendance.sync.running', ['pid' => getmypid(), 'at' => now()->toDateTimeString()], now()->addMinutes(20));
        $device = new ZktecoDevice(config('attendance.zkteco.host'), (int) config('attendance.zkteco.port', 4370), config('attendance.zkteco.comm_key'));
        try {
            if (! $device->connect()) {
                throw new \RuntimeException('The scanner did not answer.');
            }
            $device->setReadTimeout(60);
            $device->disableDevice(); // nobody punches mid-clear

            $punches = $device->readAttendance();
            $this->line('  Punches read: '.count($punches));
            if (count($punches) < 1) {
                throw new \RuntimeException('The log came back empty.');
            }

            foreach (array_chunk($punches, 1000) as $chunk) {
                DB::table('scanner_punches')->insertOrIgnore(array_map(fn ($p) => [
                    'biometric_id' => (string) $p['biometric_id'], 'punched_at' => $p['timestamp'], 'created_at' => now()], $chunk));
            }
            // Every distinct punch must be kept (the log can hold the same punch twice).
            $kept = DB::table('scanner_punches')->get(['biometric_id', 'punched_at'])
                ->map(fn ($r) => $r->biometric_id.'|'.$r->punched_at)->flip();
            $distinct = collect($punches)->map(fn ($p) => $p['biometric_id'].'|'.$p['timestamp'])->unique();
            $missing = $distinct->reject(fn ($k) => isset($kept[$k]))->count();
            $this->line('  Distinct punches: '.$distinct->count().", missing from the archive: {$missing}");
            if ($missing > 0) {
                $this->error('  Not every punch is archived - stopped, nothing cleared.');

                return false;
            }

            $before = $device->getUser();
            $peopleBefore = is_array($before) ? count($before) : 0;
            $this->line("  People on the scanner: {$peopleBefore}");
            if ($peopleBefore < 250) {
                $this->error('  The people list did not read in full - stopped, nothing cleared.');

                return false;
            }

            $device->clearAttendance(); // CMD_CLEAR_ATT_LOG only
            $left = count($device->readAttendance());
            $after = $device->getUser();
            $peopleAfter = is_array($after) ? count($after) : 0;
            $this->info("  Cleared. Punches left: {$left}. People now: {$peopleAfter}".($peopleAfter === $peopleBefore ? ' (all still there).' : ' - CHECK THE SCANNER, the count changed!'));

            // The last few days become attendance days, as a sync would.
            $importer->import(array_values(array_filter($punches, fn ($p) => $p['timestamp'] >= now()->subDays(3)->toDateString())));
            Cache::forever('attendance.sync.last_ok_at', now()->toDateTimeString());
            Auditor::record('delete', 'scanner_attendance_log', 0,
                ['punches_on_scanner' => count($punches), 'people' => $peopleBefore],
                ['punches_on_scanner' => $left, 'people' => $peopleAfter, 'note' => 'attendance log cleared; people and fingerprints kept; every punch archived']);

            return true;
        } finally {
            try { $device->enableDevice(); } catch (\Throwable) {}
            try { $device->disconnect(); } catch (\Throwable) {}
            Cache::forget('attendance.sync.running');
        }
    }
}
