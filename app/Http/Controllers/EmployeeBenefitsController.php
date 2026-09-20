<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeBenefitsController extends Controller
{
    public function index()
    {
        $employeeId = PeopleAccess::employeeId();
        $policy = $this->activePolicy();
        $usage = Schema::hasTable('employee_discount_usages')
            ? DB::table('employee_discount_usages')->where('employee_id', $employeeId)->orderByDesc('purchase_date')->limit(12)->get()
            : collect();

        return view('employee.benefits', compact('policy', 'usage'));
    }

    private function activePolicy()
    {
        if (!Schema::hasTable('employee_discount_policies')) return null;

        $today = now()->toDateString();
        return DB::table('employee_discount_policies')
            ->where('active', true)
            ->where(function ($q) use ($today) { $q->whereNull('effective_from')->orWhere('effective_from', '<=', $today); })
            ->where(function ($q) use ($today) { $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today); })
            ->orderByDesc('effective_from')
            ->first();
    }
}
