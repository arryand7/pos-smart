<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('gate_user_uuid')->nullable()->unique()->after('sso_synced_at');
            $table->string('status', 20)->default('active')->index()->after('gate_user_uuid');
            $table->timestamp('last_gate_synced_at')->nullable()->after('status');
            $table->string('gate_photo_checksum', 128)->nullable()->after('last_gate_synced_at');
        });

        Schema::create('gate_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('preview')->index();
            $table->string('gate_response_checksum', 64);
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('applied_at')->nullable();
            $table->string('report_status', 20)->default('not_started')->index();
            $table->unsignedInteger('report_attempts')->default(0);
            $table->text('last_report_error')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gate_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('gate_sync_batches')->cascadeOnDelete();
            $table->uuid('gate_user_uuid')->nullable()->index();
            $table->foreignId('local_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->index();
            $table->string('recommended_action', 40);
            $table->string('selected_action', 40)->nullable();
            $table->json('gate_payload')->nullable();
            $table->json('local_payload')->nullable();
            $table->json('differences')->nullable();
            $table->string('result_status', 30)->nullable()->index();
            $table->string('external_user_id')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_sync_items');
        Schema::dropIfExists('gate_sync_batches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['gate_user_uuid']);
            $table->dropIndex(['status']);
            $table->dropColumn(['gate_user_uuid', 'status', 'last_gate_synced_at', 'gate_photo_checksum']);
        });
    }
};
