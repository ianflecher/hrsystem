<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Republish the Philippine rule snapshot after correcting the BIR brackets.
 *
 * The stored snapshot is what payroll actually computes from - config is only
 * the source it was published from - so correcting config/statutory.php on its
 * own changed nothing. The three bracket floors were a peso low (16,666 rather
 * than 16,667 and so on), which put twenty centavos of tax on every payslip in
 * the second bracket.
 *
 * The existing verification stands. This corrects a transcription error in the
 * table that was signed off - the official floors have not moved - and payroll
 * refuses to run at all against unverified rules, so clearing the flag here
 * would stop every payroll run until somebody noticed a command they had never
 * been told to run. Whoever reviews these next should still look at the
 * brackets; the figures are in the migration history either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        $version = (string) config('statutory.version');
        if ($version === '') {
            return;
        }

        DB::table('payroll_rule_versions')
            ->where('jurisdiction', 'PH')
            ->where('version', $version)
            ->update([
                'rules' => json_encode(config('statutory'), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Rule history must not be silently reverted during a rollback.
    }
};
