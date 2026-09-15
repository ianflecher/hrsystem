<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Who changed what, and when.
 *
 * The table existed and only the applications screen ever wrote to it, so the
 * two places that actually matter - somebody's hours and somebody's pay - left
 * no trace at all. When a person disputes a deduction, the answer has to be
 * better than "the system says so".
 *
 * Writing an audit entry must never be what breaks the thing being audited, so
 * a failure here is logged and swallowed rather than thrown: losing the record
 * of a change is bad, losing the change is worse.
 */
class Auditor
{
    public static function record(string $action, string $table, int|string|null $recordId,
        ?array $before = null, ?array $after = null): void
    {
        try {
            DB::table('audit_logs')->insert([
                'action'     => $action,
                'table_name' => $table,
                'record_id'  => $recordId,
                'old_values' => $before === null ? null : json_encode($before),
                'new_values' => $after === null ? null : json_encode($after),
                'user_id'    => auth()->id(),
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not write an audit entry', [
                'action' => $action, 'table' => $table, 'record' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The history of one record, newest first, with who did it.
     */
    public static function history(string $table, int|string $recordId, int $limit = 20)
    {
        return DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.user_id')
            ->where('a.table_name', $table)
            ->where('a.record_id', $recordId)
            ->orderByDesc('a.id')
            ->limit($limit)
            ->select('a.*', 'u.full_name')
            ->get();
    }

    /**
     * Everything recent, for the trail HR reads.
     */
    public static function recent(array $tables = [], int $limit = 50)
    {
        return DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.user_id', '=', 'a.user_id')
            ->when($tables, fn ($q) => $q->whereIn('a.table_name', $tables))
            ->orderByDesc('a.id')
            ->limit($limit)
            ->select('a.*', 'u.full_name')
            ->get();
    }
}
