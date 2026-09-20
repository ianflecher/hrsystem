<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('approval_actions')) {
            Schema::create('approval_actions', function (Blueprint $t) {
                $t->id();
                $t->string('category', 40);
                $t->string('reference_type', 80);
                $t->unsignedBigInteger('reference_id');
                $t->string('action', 30);
                $t->foreignId('acted_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->text('notes')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->timestamps();
                $t->index(['category', 'action'], 'aa_category_action_idx');
                $t->index(['reference_type', 'reference_id'], 'aa_reference_idx');
            });
        }

        if (!Schema::hasTable('security_events')) {
            Schema::create('security_events', function (Blueprint $t) {
                $t->id();
                $t->string('event_type', 60);
                $t->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
                $t->string('route', 160)->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->text('details')->nullable();
                $t->timestamps();
                $t->index(['event_type', 'created_at'], 'se_event_type_created_idx');
                $t->index(['user_id', 'created_at'], 'se_user_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('approval_actions');
    }
};
