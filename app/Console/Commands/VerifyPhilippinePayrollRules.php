<?php

namespace App\Console\Commands;

use App\Support\Statutory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyPhilippinePayrollRules extends Command
{
    protected $signature = 'payroll:verify-ph-rules {--version= : Rule version to verify} {--date= : Effective date to verify}';
    protected $description = 'Mark an effective Philippine payroll rule version as reviewed by an authorized administrator.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $snapshot = Statutory::snapshot($date);
        $version = $this->option('version') ?: $snapshot['version'];

        $updated = DB::table('payroll_rule_versions')
            ->where('jurisdiction', 'PH')
            ->where('version', $version)
            ->update(['verified_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            $this->error("Philippine payroll rule version {$version} was not found.");
            return self::FAILURE;
        }

        $this->info("Verified Philippine payroll rules: {$version} effective {$snapshot['effective_from']}.");
        $this->warn('Only run this command after an authorized reviewer has checked the cited SSS, PhilHealth, Pag-IBIG, BIR and DOLE issuances.');
        return self::SUCCESS;
    }
}
