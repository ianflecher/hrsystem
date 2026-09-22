<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the employee masterlist carries that the record had nowhere to keep.
 *
 * Contact number and address are how somebody is reached when there is an
 * accident; the bank account is how they are paid. Keeping them only in a
 * spreadsheet means the system cannot do either.
 *
 * AWOL is a real status here and was not one of the four the enum allowed, so
 * it could only have been recorded as something it is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('contact_number', 40)->nullable()->after('biometric_id');
            $table->string('address', 255)->nullable()->after('contact_number');
            $table->string('gender', 20)->nullable()->after('birth_date');
            $table->string('bank_account', 40)->nullable()->after('address');
        });

        DB::statement("ALTER TABLE employees MODIFY status ENUM('active','inactive','terminated','on_leave','awol') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::table('employees')->where('status', 'awol')->update(['status' => 'inactive']);
        DB::statement("ALTER TABLE employees MODIFY status ENUM('active','inactive','terminated','on_leave') NOT NULL DEFAULT 'active'");

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'address', 'gender', 'bank_account']);
        });
    }
};
