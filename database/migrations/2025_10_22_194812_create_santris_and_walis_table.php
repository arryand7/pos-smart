<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('walis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->json('notification_channels')->nullable();
            $table->timestamps();
        });

        Schema::create('santris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('wali_id')->nullable()->constrained('walis')->nullOnDelete();
            $table->string('nis')->unique();
            $table->string('nisn')->nullable();
            $table->string('name');
            $table->string('nickname')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('class')->nullable();
            $table->string('dormitory')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->decimal('daily_limit', 12, 2)->default(0);
            $table->decimal('monthly_limit', 12, 2)->default(0);
            $table->timestamp('limit_reset_at')->nullable();
            $table->boolean('is_wallet_locked')->default(false);
            $table->json('blocked_category_ids')->nullable();
            $table->json('whitelisted_category_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('santris');
        Schema::dropIfExists('walis');
    }
};
