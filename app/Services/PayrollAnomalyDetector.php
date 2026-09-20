<?php

namespace App\Services;

use App\Support\PayPeriod;
use Illuminate\Support\Facades\DB;

class PayrollAnomalyDetector
{
    public function refresh(PayPeriod $period): int
    {
        DB::table('payroll_anomalies')->where('period_start', $period->start)->where('resolved', false)->delete();
        $previous = PayPeriod::fromStart($this->previousStart($period));
        $current = DB::table('hr_payroll')->where('period_start', $period->start)->get();
        $prior = DB::table('hr_payroll')->where('period_start', $previous->start)->get()->keyBy('employee_id');
        $count = 0;
        foreach ($current as $row) {
            $old = $prior[$row->employee_id] ?? null;
            if (!$old) continue;
            $oldNet = (float)$old->net_pay; $newNet = (float)$row->net_pay;
            if ($oldNet != 0 && abs(($newNet - $oldNet) / $oldNet) >= 0.25) {
                DB::table('payroll_anomalies')->insert([
                    'employee_id' => $row->employee_id, 'period_start' => $period->start, 'type' => 'net_pay_variance',
                    'severity' => abs(($newNet - $oldNet) / $oldNet) >= 0.50 ? 'high' : 'medium',
                    'message' => 'Net pay changed by '.number_format(abs(($newNet - $oldNet) / $oldNet) * 100, 1).'% from the previous comparable payroll.',
                    'current_value' => $newNet, 'previous_value' => $oldNet, 'created_at' => now(), 'updated_at' => now(),
                ]); $count++;
            }
        }
        return $count;
    }
    private function previousStart(PayPeriod $period): string
    {
        return date('Y-m-d', strtotime($period->start.' -15 days'));
    }
}
