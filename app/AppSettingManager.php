<?php

namespace App;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AppSettingManager
{
    public static function current(): AppSetting
    {
        $defaults = [
            'sso_base_url' => config('sso.base_url'),
            'sso_client_id' => config('sso.client_id'),
            'sso_client_secret' => config('sso.client_secret'),
            'sso_redirect_uri' => config('sso.redirect_uri'),
            'sso_scopes' => config('sso.scopes', 'openid profile email roles'),
        ];

        if (!Schema::hasTable('app_settings')) {
            return new AppSetting($defaults);
        }

        /** @var AppSetting|null $setting */
        $setting = Cache::rememberForever('app.setting.current', function () {
            return AppSetting::query()->first();
        });

        if (!$setting) {
            $setting = new AppSetting($defaults);
        }

        foreach ($defaults as $key => $value) {
            if ($setting->{$key} === null && $value !== null) {
                $setting->{$key} = $value;
            }
        }

        return $setting;
    }

    public static function refreshCache(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        Cache::forget('app.setting.current');
    }
}
