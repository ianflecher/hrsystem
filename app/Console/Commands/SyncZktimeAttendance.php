<?php

namespace App\Console\Commands;

use App\Services\Attendance\PunchImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class SyncZktimeAttendance extends Command
{
    protected $signature = 'attendance:sync-zktime
                            {--mdb= : Path to ZKTime att2000.mdb}
                            {--overwrite : Replace days somebody entered by hand}';

    protected $description = 'Import attendance downloaded by ZKTime / Attendance Management Program';

    public function handle(PunchImporter $importer): int
    {
        $source = $this->option('mdb') ?: config('attendance.zktime.mdb_path');

        if (! $source || ! is_file($source)) {
            $this->error("ZKTime database not found: {$source}");

            return self::FAILURE;
        }

        $snapshot = Storage::path('zktime-att2000-import.mdb');
        $json = Storage::path('zktime-punches.json');
        $script = Storage::path('read-zktime-attendance.ps1');

        if (! @copy($source, $snapshot)) {
            $this->error("Could not copy the ZKTime database from {$source}. Close ZKTime and try again.");

            return self::FAILURE;
        }

        file_put_contents($script, $this->readerScript());

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            $snapshot,
            $json,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Could not read the ZKTime database.');

            return self::FAILURE;
        }

        $payload = (string) @file_get_contents($json);
        $payload = preg_replace('/^\xEF\xBB\xBF/', '', $payload) ?? $payload;
        $punches = json_decode($payload, true);

        if (! is_array($punches)) {
            $this->error('ZKTime did not produce readable attendance data.');

            return self::FAILURE;
        }

        $this->info('Found '.count($punches).' punch(es) in ZKTime.');

        $summary = $importer->import($punches, (bool) $this->option('overwrite'));

        $this->newLine();
        $this->info("Wrote {$summary['days']} day(s) across {$summary['employees']} employee(s).");

        if ($summary['unknown']) {
            $this->newLine();
            $this->warn('These badge/enrolment numbers are in ZKTime but not linked to anybody in HRIS:');
            $this->line('  '.implode(', ', $summary['unknown']));
            $this->line('  Put the matching number in the employee Scanner ID / Biometric ID field.');
        }

        if ($summary['skipped']) {
            $this->line("Skipped {$summary['skipped']} punch(es).");
        }

        return self::SUCCESS;
    }

    private function readerScript(): string
    {
        return <<<'PS1'
param(
    [Parameter(Mandatory=$true)][string]$DatabasePath,
    [Parameter(Mandatory=$true)][string]$OutputPath
)

$ErrorActionPreference = 'Stop'

$conn = New-Object -ComObject ADODB.Connection
$conn.Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source=$DatabasePath;Persist Security Info=False;")

try {
    $sql = @"
SELECT CHECKINOUT.USERID, USERINFO.Badgenumber, CHECKINOUT.CHECKTIME
FROM CHECKINOUT
LEFT JOIN USERINFO ON CHECKINOUT.USERID = USERINFO.USERID
ORDER BY CHECKINOUT.CHECKTIME
"@

    $rs = $conn.Execute($sql)
    $rows = New-Object System.Collections.Generic.List[object]

    while (-not $rs.EOF) {
        $badge = [string]$rs.Fields.Item('Badgenumber').Value
        if ([string]::IsNullOrWhiteSpace($badge)) {
            $badge = [string]$rs.Fields.Item('USERID').Value
        }

        $stamp = [datetime]$rs.Fields.Item('CHECKTIME').Value

        $rows.Add([pscustomobject]@{
            biometric_id = $badge.Trim()
            timestamp = $stamp.ToString('yyyy-MM-dd HH:mm:ss')
        })

        $rs.MoveNext()
    }

    $rs.Close()
    $rows | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $OutputPath -Encoding UTF8
}
finally {
    $conn.Close()
}
PS1;
    }
}
