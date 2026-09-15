<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements were removed from the product, so the two tables go with it.
 *
 * The migration that created them no longer does, which covers a fresh install;
 * this covers the databases that already ran it. Both were empty when this was
 * written - the feature was never used - so nothing is lost here. There is no
 * down(): recreating empty tables for a feature that no longer has any code
 * behind it would restore nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }

    public function down(): void
    {
        //
    }
};
