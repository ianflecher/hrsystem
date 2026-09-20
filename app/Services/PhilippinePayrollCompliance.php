<?php

namespace App\Services;

use App\Support\Statutory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Central Philippine payroll compliance helpers.
 *
 * This service deliberately does not invent legal entitlements. It exposes the
 * configured/effective rule set and provides validation hooks so payroll can be
 * stopped when its rule set is incomplete or stale.
 */
class PhilippinePayrollCompliance
{
    public function status(?string $date = null): array
    {
        $date = $date ?: Carbon::today()->toDateString();
        $snapshot = Statutory::snapshot($date);
        $rules = $snapshot['rules'] ?? [];

        $required = ['sss', 'philhealth', 'pagibig', 'bir', 'holiday', 'overtime', 'nsd'];
        $missing = [];
        foreach ($required as $name) {
            if (! isset($rules[$name]) || ! is_array($rules[$name])) {
                $missing[] = $name;
            }
        }

        $verifiedAt = null;
        $sourceCount = 0;
        $ruleRowExists = false;
        try {
            $row = DB::table('payroll_rule_versions')
                ->where('jurisdiction', 'PH')
                ->where('version', $snapshot['version'])
                ->first(['id', 'verified_at', 'sources']);
            $ruleRowExists = (bool) $row;
            $verifiedAt = $row?->verified_at;
            $sources = $row && $row->sources ? (json_decode($row->sources, true) ?: []) : [];
            $sourceCount = is_array($sources) ? count(array_filter($sources, fn ($value) => is_string($value) && trim($value) !== '')) : 0;
        } catch (\Throwable) {
            // Allows arithmetic/unit tests before migrations are installed.
        }

        return [
            'date' => $date,
            'version' => $snapshot['version'],
            'effective_from' => $snapshot['effective_from'] ?? null,
            'verified_at' => $verifiedAt,
            'missing_rules' => $missing,
            'rule_row_exists' => $ruleRowExists,
            'source_count' => $sourceCount,
            'verified' => $ruleRowExists && ! empty($verifiedAt) && $sourceCount >= 4,
            'ready' => $missing === [] && $ruleRowExists && ! empty($verifiedAt) && $sourceCount >= 4,
        ];
    }

    /**
     * Throw before a production payroll is generated when mandatory PH rules
     * are missing. A stale verification is reported, but not guessed around.
     */
    public function assertReady(?string $date = null): array
    {
        $status = $this->status($date);
        if (! $status['ready']) {
            $reasons = $status['missing_rules'];
            if (! $status['rule_row_exists']) $reasons[] = 'no effective Philippine rule version';
            if (empty($status['verified_at'])) $reasons[] = 'rules have not been verified';
            if (($status['source_count'] ?? 0) < 4) $reasons[] = 'statutory source references are incomplete';
            throw new \RuntimeException('Philippine payroll compliance is not ready: '.implode(', ', array_unique($reasons)));
        }
        return $status;
    }

    public function employeeGovernmentIds(object $employee): array
    {
        return [
            'sss' => $this->mask($employee->sss_number ?? null),
            'philhealth' => $this->mask($employee->philhealth_number ?? null),
            'pagibig' => $this->mask($employee->pagibig_number ?? null),
            'tin' => $this->mask($employee->tin ?? null),
        ];
    }

    private function mask(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $visible = min(4, strlen($value));
        return str_repeat('•', max(0, strlen($value) - $visible)).substr($value, -$visible);
    }
}
