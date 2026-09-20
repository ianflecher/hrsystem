<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => false,
            'employees_table' => Schema::hasTable('employees'),
            'payroll_table' => Schema::hasTable('hr_payroll'),
            'payroll_controls' => Schema::hasTable('payroll_period_controls'),
            'security_events' => Schema::hasTable('security_events'),
            'approval_actions' => Schema::hasTable('approval_actions'),
        ];
        try { DB::select('select 1'); $checks['database'] = true; } catch (\Throwable) {}
        $ok = !in_array(false, $checks, true);
        return response()->json(['status'=>$ok?'ok':'degraded','checks'=>$checks,'timestamp'=>now()->toIso8601String()], $ok?200:503);
    }
}
