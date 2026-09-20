<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnsureEmployeeDiscountPolicy extends Command
{
    protected $signature = 'hris:ensure-discount-policy {--force : Reactivate/update the standard 50% policy}';
    protected $description = 'Ensure the standard Imprint Customs 50% employee product discount policy exists.';

    public function handle(): int
    {
        if (!Schema::hasTable('employee_discount_policies')) {
            $this->error('Run php artisan migrate first.');
            return self::FAILURE;
        }

        $policy = DB::table('employee_discount_policies')->orderByDesc('id')->first();
        if (!$policy) {
            DB::table('employee_discount_policies')->insert([
                'name' => 'Imprint Customs Employee Product Discount',
                'discount_percent' => 50,
                'active' => true,
                'usage_rules' => 'Eligible active employees receive 50% off eligible Imprint Customs products, subject to current company policy and product exclusions.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->info('Created the standard 50% employee discount policy.');
            return self::SUCCESS;
        }

        if ($this->option('force')) {
            DB::table('employee_discount_policies')->where('id', $policy->id)->update(['discount_percent'=>50,'active'=>true,'updated_at'=>now()]);
            $this->info('Standard 50% policy reactivated.');
        } else {
            $this->line('An employee discount policy already exists; no changes made.');
        }
        return self::SUCCESS;
    }
}
