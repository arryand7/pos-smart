<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('role')
                ->constrained('locations')->nullOnDelete();
            $table->index(['role', 'location_id'], 'users_cashier_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_cashier_location_idx');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
