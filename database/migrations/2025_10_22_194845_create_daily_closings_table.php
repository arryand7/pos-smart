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
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->index();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kasir_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('cash_opening_balance', 12, 2)->default(0);
            $table->decimal('cash_total_sales', 12, 2)->default(0);
            $table->decimal('cash_total_topups', 12, 2)->default(0);
            $table->decimal('cash_deposited', 12, 2)->default(0);
            $table->decimal('cash_variance', 12, 2)->default(0);
            $table->decimal('wallet_sales', 12, 2)->default(0);
            $table->decimal('gateway_sales', 12, 2)->default(0);
            $table->decimal('total_transactions', 12, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('report_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
