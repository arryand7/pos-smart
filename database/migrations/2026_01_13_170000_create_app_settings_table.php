<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('sso_base_url')->nullable();
            $table->string('sso_client_id')->nullable();
            $table->string('sso_client_secret')->nullable();
            $table->string('sso_redirect_uri')->nullable();
            $table->string('sso_scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
