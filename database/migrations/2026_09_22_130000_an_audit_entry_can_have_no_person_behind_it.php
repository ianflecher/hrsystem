<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * audit_logs.user_id was NOT NULL, so anything not done by a signed-in person
 * could not be recorded: a console command, a scheduled job, a system
 * correction. Auditor swallows its own failures on purpose - losing the record
 * of a change should never lose the change - so those entries did not error,
 * they simply never appeared.
 *
 * A system action genuinely has nobody behind it, and the honest way to say so
 * is a null rather than a fabricated user.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audit_logs MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Rows written by the system have no user to put back, so they would
        // block the column being made NOT NULL again.
        DB::table('audit_logs')->whereNull('user_id')->delete();
        DB::statement('ALTER TABLE audit_logs MODIFY user_id BIGINT UNSIGNED NOT NULL');
    }
};
