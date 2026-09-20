<?php

namespace App\Services;

class CertificateOfEmployment
{
    public function data(object $employee): array
    {
        return [
            'employee_id' => $employee->employee_id,
            'name' => $employee->full_name,
            'position' => $employee->job_title,
            'hire_date' => $employee->hire_date,
            'status' => $employee->status,
            'employment_type' => $employee->employment_type ?? 'regular',
            'issued_on' => now()->toDateString(),
        ];
    }
}
