<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep a profile picture.
 *
 * Only the path is stored; the file itself goes on the public disk, which is
 * why `php artisan storage:link` is part of setup. Imprint Production keeps
 * the same column for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path', 2048)->nullable()->after('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
