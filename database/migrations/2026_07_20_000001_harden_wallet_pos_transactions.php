<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('client_transaction_id')->nullable()->after('reference');
            $table->unique('client_transaction_id');
            $table->index(['santri_id', 'status', 'processed_at'], 'transactions_wallet_limit_idx');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->uuid('idempotency_key')->nullable()->after('reference_id');
            $table->foreignId('reversed_transaction_id')->nullable()->after('idempotency_key')
                ->constrained('wallet_transactions')->nullOnDelete();
            $table->unique('uuid');
            $table->unique('idempotency_key');
            $table->index(['santri_id', 'type', 'status', 'occurred_at'], 'wallet_limit_lookup_idx');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('journal_type', 30)->default('primary')->after('source_id');
        });
        DB::table('journal_entries')->where('reference', 'like', 'REV-%')->update(['journal_type' => 'reversal']);
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['source_type', 'source_id', 'journal_type'], 'journal_source_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_source_type_unique');
            $table->dropColumn('journal_type');
        });
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index('santri_id', 'wallet_transactions_santri_rollback_idx');
            $table->dropIndex('wallet_limit_lookup_idx');
            $table->dropUnique(['uuid']);
            $table->dropUnique(['idempotency_key']);
            $table->dropConstrainedForeignId('reversed_transaction_id');
            $table->dropColumn(['uuid', 'idempotency_key']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('santri_id', 'transactions_santri_rollback_idx');
            $table->dropIndex('transactions_wallet_limit_idx');
            $table->dropUnique(['client_transaction_id']);
            $table->dropColumn('client_transaction_id');
        });
    }
};
