<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When HR creates an account it has to set a first password, which means
 * somebody other than the account holder knows it. This flag forces that
 * password to be replaced before the account can be used for anything else.
 *
 * Defaults to false, so every account that already exists is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
