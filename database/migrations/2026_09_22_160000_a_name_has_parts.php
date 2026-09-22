<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filipino staff lists are read by surname, and full_name is "First Middle
 * Surname", so ordering by it ordered by first name.
 *
 * The surname cannot be recovered from the whole name afterwards: the last
 * word of "Moises John Montes Rebato III" is a suffix, and "Dina De La Cruz
 * Lopez" has a multi-word middle name. It has to be kept as it was given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable()->after('full_name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_name']);
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
