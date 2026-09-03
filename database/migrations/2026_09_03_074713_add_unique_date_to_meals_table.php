<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meal generation relies on there being at most one meal per date - an
     * invariant the Copy Meal action already assumed but nothing enforced.
     */
    public function up(): void
    {
        $duplicates = DB::table('meals')
            ->select('date')
            ->groupBy('date')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('date');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add a unique index on meals.date: these dates have more than one meal: '
                . $duplicates->implode(', ')
                . '. Merge them by hand, then re-run this migration.'
            );
        }

        Schema::table('meals', function (Blueprint $table) {
            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropUnique(['date']);
        });
    }
};
