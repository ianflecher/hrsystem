<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_assignments')) {
            Schema::create('shift_assignments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('employee_id')->constrained('employees', 'employee_id')->cascadeOnDelete();
                $t->date('work_date');
                $t->time('starts_at')->nullable();
                $t->time('ends_at')->nullable();
                $t->boolean('rest_day')->default(false);
                $t->string('label', 80);
                $t->timestamps();
                $t->unique(['employee_id', 'work_date']);
            });
        }

        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $t) {
                $t->id();
                $t->string('title', 160);
                $t->text('body');
                $t->foreignId('department_id')->nullable()->constrained('departments', 'department_id')->nullOnDelete();
                $t->timestamp('published_at')->nullable()->index();
                $t->timestamp('expires_at')->nullable()->index();
                $t->boolean('archived')->default(false)->index();
                $t->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('announcement_reads')) {
            Schema::create('announcement_reads', function (Blueprint $t) {
                $t->id();
                $t->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
                $t->timestamp('acknowledged_at');
                $t->timestamps();
                $t->unique(['announcement_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('shift_assignments');
    }
};
