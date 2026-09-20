<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class SchemaReady
{
    public static function payrollRuleVersions(): bool
    {
        try { return Schema::hasTable('payroll_rule_versions'); }
        catch (\Throwable) { return false; }
    }
}
