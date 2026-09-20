<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SecurityAudit
{
    public static function record(string $event, ?string $details = null): void
    {
        if (!Schema::hasTable('security_events')) return;
        DB::table('security_events')->insert([
            'event_type' => $event,
            'user_id' => auth()->id(),
            'route' => request()->route()?->getName(),
            'ip_address' => request()->ip(),
            'details' => $details,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
