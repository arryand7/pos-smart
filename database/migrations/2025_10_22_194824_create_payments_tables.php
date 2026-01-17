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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_reference')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('status', 30)->default('pending');
            $table->string('payment_method', 30)->nullable();
            $table->string('channel', 30)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->nullableMorphs('payable');
            $table->foreignId('santri_id')->nullable()->constrained('santris')->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('priority')->default(1);
            $table->json('config')->nullable();
            $table->json('sandbox_config')->nullable();
            $table->string('webhook_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event')->nullable();
            $table->string('signature')->nullable();
            $table->text('endpoint')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payment_provider_configs');
        Schema::dropIfExists('payments');
    }
};
