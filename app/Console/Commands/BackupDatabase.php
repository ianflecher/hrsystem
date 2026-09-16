<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Dumps the database to a file, and keeps the last few.
 *
 * Payroll history is the kind of data nobody notices is gone until they need
 * it, and it cannot be reconstructed from anything else. This is deliberately
 * the dullest possible backup - mysqldump to a local folder - because a backup
 * nobody has set up is worth nothing, and this one needs no account anywhere.
 *
 * It is NOT off-site. A dump sitting on the same machine as the database
 * survives a mistaken DROP or a bad migration, and does not survive the disk
 * dying or the office flooding. Copy these somewhere else as well.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--keep=14 : How many dumps to keep}
        {--path= : Where to write them (default storage/app/backups)}';

    protected $description = 'Dump the database to a file and prune old dumps';

    public function handle(): int
    {
        $config = config('database.connections.'.config('database.default'));

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Only MySQL/MariaDB is supported here.');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('app/backups');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error('Could not create '.$directory);

            return self::FAILURE;
        }

        $file = $directory.DIRECTORY_SEPARATOR.$config['database'].'-'.now()->format('Y-m-d_His').'.sql';
        $binary = $this->binary();

        // The password goes in the environment, never on the command line,
        // where it would be visible to anybody running a process list.
        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--quick',
            '--default-character-set=utf8mb4',
            '--result-file='.$file,
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']], null, 600);

        $this->info('Dumping '.$config['database'].' to '.basename($file));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            $this->error('The dump failed: '.trim($process->getErrorOutput() ?: 'no output'));
            $this->line('If mysqldump is not on PATH, set DB_DUMP_BINARY in .env to its full path.');

            return self::FAILURE;
        }

        $this->info('Wrote '.number_format(filesize($file) / 1024, 1).' KB');
        $this->prune($directory, max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }

    /**
     * Where mysqldump is.
     *
     * DB_DUMP_BINARY wins if it is set. Otherwise the usual places are tried,
     * because on Windows mysqldump is almost never on PATH and a backup that
     * needs a setting nobody knows about is a backup nobody has.
     */
    private function binary(): string
    {
        $configured = env('DB_DUMP_BINARY');

        if ($configured) {
            return $configured;
        }

        foreach ([
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp1/mysql/bin/mysqldump.exe',
            'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe',
            'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Nothing found; PATH is the last hope, and its failure message is the
        // one that tells the user to set DB_DUMP_BINARY.
        return 'mysqldump';
    }

    private function prune(string $directory, int $keep): void
    {
        $dumps = glob($directory.DIRECTORY_SEPARATOR.'*.sql') ?: [];

        if (count($dumps) <= $keep) {
            return;
        }

        // Newest first, then drop whatever falls past the limit.
        usort($dumps, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($dumps, $keep) as $old) {
            @unlink($old);
            $this->line('Removed '.basename($old));
        }
    }
}
