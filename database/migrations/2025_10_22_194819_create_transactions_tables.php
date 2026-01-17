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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type', 30)->default('pos')->index();
            $table->string('channel', 30)->default('pos')->index();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kasir_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('santri_id')->nullable()->constrained('santris')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('wallet_amount', 12, 2)->default(0);
            $table->decimal('gateway_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('primary_payment_method', 30)->nullable();
            $table->json('payment_breakdown')->nullable();
            $table->boolean('requires_sync')->default(false);
            $table->string('offline_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku', 50)->nullable();
            $table->string('product_barcode', 100)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
