<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemHealth extends Command
{
    protected $signature = 'hris:health';
    protected $description = 'Check database and required HRIS tables.';

    public function handle(): int
    {
        try { DB::select('select 1'); $this->info('Database: OK'); }
        catch (\Throwable $e) { $this->error('Database: FAIL - '.$e->getMessage()); return self::FAILURE; }
        $tables=['employees','hr_payroll','payroll_period_controls','security_events','approval_actions'];
        $failed=[];
        foreach($tables as $table){ $ok=Schema::hasTable($table); $this->line(($ok?'OK ':'FAIL ').$table); if(!$ok)$failed[]=$table; }
        if($failed){ $this->error('Missing required tables: '.implode(', ',$failed)); return self::FAILURE; }
        $this->info('HRIS health check passed.'); return self::SUCCESS;
    }
}
