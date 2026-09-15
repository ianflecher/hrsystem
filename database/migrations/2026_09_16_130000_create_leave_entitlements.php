<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many days of each kind of leave a year is worth.
 *
 * Deliberately empty to begin with. What a company grants is its own policy -
 * the law sets a floor of five days' service incentive leave after a year, and
 * most companies grant more - so nothing is assumed here. Until HR enters an
 * entitlement for a type, that type simply has no limit and the screens say so
 * rather than inventing a number.
 *
 * after_months carries the service requirement: service incentive leave is
 * earned after twelve months, and somebody who has not reached it is not yet
 * entitled.
 *
 * NOT HANDLED: carry-over between years, pro-rating a first partial year, and
 * cash conversion of unused days. Each is a policy decision, and guessing at
 * them would put wrong numbers in front of people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $t) {
            $t->id();
            $t->string('leave_type', 30)->unique();
            $t->decimal('days_per_year', 5, 1);
            $t->unsignedSmallInteger('after_months')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }
};
