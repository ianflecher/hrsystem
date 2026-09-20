<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $version = (string) config('statutory.version');
        if ($version === '') return;

        DB::table('payroll_rule_versions')
            ->where('jurisdiction', 'PH')
            ->where('version', 'PH-STAT-2025-01')
            ->update([
                'version' => $version,
                'rules' => json_encode(config('statutory'), JSON_THROW_ON_ERROR),
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Rule history must not be silently reverted during a rollback.
    }
};
