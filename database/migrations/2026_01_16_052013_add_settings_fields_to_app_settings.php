<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Email/SMTP settings
            $table->string('mail_mailer')->nullable();
            $table->string('mail_host')->nullable();
            $table->integer('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->string('mail_password')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();

            // Branding settings
            $table->string('app_name')->nullable();
            $table->string('app_logo')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('tagline')->nullable();
            $table->text('footer_text')->nullable();

            // Accounting settings
            $table->string('account_cash')->nullable();
            $table->string('account_wallet_liability')->nullable();
            $table->string('account_revenue')->nullable();
            $table->string('account_inventory')->nullable();
            $table->string('account_cogs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
                'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name',
                'app_name', 'app_logo', 'primary_color', 'accent_color', 'tagline', 'footer_text',
                'account_cash', 'account_wallet_liability', 'account_revenue', 'account_inventory', 'account_cogs',
            ]);
        });
    }
};
