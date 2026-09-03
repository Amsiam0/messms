<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expenses previously had no date of their own; reports and filters keyed
     * off created_at, so an expense could not be recorded against the day it
     * was actually spent.
     *
     * Existing rows are backfilled from created_at, which is exactly what the
     * reports were already using - no total changes.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('date')->nullable()->after('amount');
        });

        DB::table('expenses')->update(['date' => DB::raw('DATE(created_at)')]);

        Schema::table('expenses', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
