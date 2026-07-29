<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApplyGateUserSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') === true;
    }

    public function rules(): array
    {
        return ['items' => ['nullable', 'array'], 'items.*.id' => ['required', 'integer'], 'items.*.action' => ['required', 'string', 'in:no_change,update_identity,create_local_user,suspend_local_user,reactivate_local_user,skip,manual_review']];
    }
}
