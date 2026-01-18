<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Location;
use App\Models\PaymentProviderConfig;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Santri;
use App\Models\User;
use App\Models\Wali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ActivityLogObserver
{
    /**
     * Attributes that should never be recorded in activity logs.
     */
    protected array $ignored = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'current_team_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'last_login_at',
        'metadata',
        'tags',
        'config',
        'sandbox_config',
        'webhook_key',
        'sso_client_secret',
        'sso_client_id',
        'sso_base_url',
        'sso_redirect_uri',
        'sso_scopes',
    ];

    public function created(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        ActivityLog::log(
            'created',
            $this->buildDescription('created', $model),
            $model,
            ['after' => $this->sanitize($model->getAttributes())]
        );
    }

    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $changes = $this->sanitize($model->getChanges());

        if (empty($changes)) {
            return;
        }

        $original = $this->sanitize(Arr::only($model->getOriginal(), array_keys($changes)));

        ActivityLog::log(
            'updated',
            $this->buildDescription('updated', $model),
            $model,
            ['before' => $original, 'after' => $changes]
        );
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        ActivityLog::log(
            'deleted',
            $this->buildDescription('deleted', $model),
            $model,
            ['before' => $this->sanitize($model->getAttributes())]
        );
    }

    protected function shouldSkip(Model $model): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return $model instanceof ActivityLog;
    }

    protected function sanitize(array $attributes): array
    {
        return Arr::except($attributes, $this->ignored);
    }

    protected function buildDescription(string $action, Model $model): string
    {
        $labels = [
            Product::class => 'Produk',
            ProductCategory::class => 'Kategori Produk',
            Location::class => 'Lokasi',
            Santri::class => 'Santri',
            Wali::class => 'Wali',
            User::class => 'Pengguna',
            AppSetting::class => 'Pengaturan',
            PaymentProviderConfig::class => 'Payment Gateway',
        ];

        $verbs = [
            'created' => 'ditambahkan',
            'updated' => 'diperbarui',
            'deleted' => 'dihapus',
        ];

        $label = $labels[$model::class] ?? class_basename($model);
        $identifier = $model->name
            ?? $model->title
            ?? $model->code
            ?? $model->sku
            ?? $model->email
            ?? $model->nis
            ?? '#'.$model->getKey();

        $verb = $verbs[$action] ?? $action;

        return sprintf('%s %s %s.', $label, $identifier, $verb);
    }
}
