<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('santris', function (Blueprint $table) {
            $table->decimal('weekly_limit', 12, 2)->default(200000)->after('daily_limit');
        });

        DB::table('santris')->whereNull('weekly_limit')->update(['weekly_limit' => 200000]);
    }

    public function down(): void
    {
        Schema::table('santris', function (Blueprint $table) {
            $table->dropColumn('weekly_limit');
        });
    }
};
