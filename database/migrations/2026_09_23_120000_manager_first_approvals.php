<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE leaves MODIFY status ENUM('pending','pending_hr','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('leaves', 'manager_reviewed_by')) {
                $table->foreignId('manager_reviewed_by')->nullable()->after('attachment_path')
                    ->constrained('users', 'user_id')->nullOnDelete();
                $table->timestamp('manager_reviewed_at')->nullable()->after('manager_reviewed_by');
                $table->text('manager_decision_note')->nullable()->after('manager_reviewed_at');
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'manager_reviewed_by')) {
                $table->foreignId('manager_reviewed_by')->nullable()->after('decision_note')
                    ->constrained('users', 'user_id')->nullOnDelete();
                $table->timestamp('manager_reviewed_at')->nullable()->after('manager_reviewed_by');
                $table->text('manager_decision_note')->nullable()->after('manager_reviewed_at');
            }
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_assignments', 'status')) {
                $table->string('status', 20)->default('approved')->after('label')->index();
                $table->foreignId('created_by')->nullable()->after('status')
                    ->constrained('users', 'user_id')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->after('created_by')
                    ->constrained('users', 'user_id')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('shift_assignments', 'status')) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn(['status', 'approved_at']);
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_requests', 'manager_reviewed_by')) {
                $table->dropConstrainedForeignId('manager_reviewed_by');
                $table->dropColumn(['manager_reviewed_at', 'manager_decision_note']);
            }
        });

        Schema::table('leaves', function (Blueprint $table) {
            if (Schema::hasColumn('leaves', 'manager_reviewed_by')) {
                $table->dropConstrainedForeignId('manager_reviewed_by');
                $table->dropColumn(['manager_reviewed_at', 'manager_decision_note']);
            }
        });

        DB::statement("ALTER TABLE leaves MODIFY status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
