<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * departments.manager_id holds whoever heads a department, and nothing in the
 * app reads or writes it yet. Renaming it now, while it is still empty and
 * unused, keeps the schema speaking the same language as the rest of the
 * system - this company has supervisors and leaders, not managers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->renameColumn('manager_id', 'supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->renameColumn('supervisor_id', 'manager_id');
        });
    }
};
