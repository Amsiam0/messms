<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stays nullable: a request submitted before this column existed has no
     * spend date, and approval falls back to the approval date for those.
     */
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->date('date')->nullable()->after('amount');
        });

        DB::table('expense_requests')->update(['date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
