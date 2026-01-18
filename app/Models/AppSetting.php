<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        // SSO
        'sso_base_url',
        'sso_client_id',
        'sso_client_secret',
        'sso_redirect_uri',
        'sso_scopes',
        // Email/SMTP
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        // Branding
        'app_name',
        'app_logo',
        'primary_color',
        'accent_color',
        'tagline',
        'footer_text',
        'timezone',
        // Accounting
        'account_cash',
        'account_wallet_liability',
        'account_revenue',
        'account_inventory',
        'account_cogs',
    ];

    protected function casts(): array
    {
        return [
            'mail_port' => 'integer',
        ];
    }

    /**
     * Get a setting value by key with optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::first();
        return $setting?->{$key} ?? $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        $setting = static::firstOrCreate([]);
        $setting->update([$key => $value]);
    }
}
