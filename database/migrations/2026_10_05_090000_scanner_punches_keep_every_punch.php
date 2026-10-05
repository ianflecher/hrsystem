<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every punch the scanner ever held, exactly as it held it: the scanner ID and
 * the time. Attendance days are built from these; this table is the copy that
 * lets the scanner's own log be cleared without losing anything - including
 * punches by IDs that are not linked to anybody here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_punches', function (Blueprint $table) {
            $table->id();
            $table->string('biometric_id', 32);
            $table->dateTime('punched_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['biometric_id', 'punched_at']);
            $table->index('punched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_punches');
    }
};
