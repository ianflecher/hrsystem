<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let holidays.classification actually be unset.
 *
 * It was added as a NOT NULL column defaulting to 'regular', but four services
 * read it as "the specific classification, or work it out from type":
 *
 *   $holiday->classification ?? ($holiday->type === 'special' ? ... )
 *
 * A column that is never null means that fallback never runs, so a holiday
 * entered as special - with nothing put in classification - was paid as a
 * regular holiday: 100% premium instead of 30%. On a 22,000 salary that is
 * about 700 pesos too much per person per special day, and the Philippines
 * has several a year.
 *
 * The column becomes nullable, and the rows where the default contradicts the
 * holiday's own type are cleared so they fall back correctly. Rows that really
 * are regular holidays say 'regular' and are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('holidays', 'classification')) {
            return;
        }

        Schema::table('holidays', function (Blueprint $table) {
            $table->string('classification', 30)->nullable()->default(null)->change();
        });

        DB::table('holidays')
            ->where('type', 'special')
            ->where('classification', 'regular')
            ->update(['classification' => null]);
    }

    public function down(): void
    {
        // Leaving the column nullable is harmless; putting the default back
        // would reintroduce the overpayment.
    }
};
