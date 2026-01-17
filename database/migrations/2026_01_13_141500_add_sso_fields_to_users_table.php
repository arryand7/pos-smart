<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_sub')->unique()->nullable()->after('remember_token');
            $table->timestamp('sso_synced_at')->nullable()->after('sso_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sso_sub', 'sso_synced_at']);
        });
    }
};
