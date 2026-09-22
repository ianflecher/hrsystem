<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One field for the bank account, not two.
 *
 * The form asked for the bank and the account number separately. The original
 * request was for one - "account number (bank)" - and one is right: people
 * write it as a single thing, and splitting it forces a choice about whether
 * "BDO Savings" belongs in the first box or the second.
 *
 * Nothing was stored in either column when this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_profiles', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('email_address');
        });
    }
};
