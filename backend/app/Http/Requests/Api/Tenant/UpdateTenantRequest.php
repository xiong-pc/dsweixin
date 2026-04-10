<?php

namespace App\Http\Requests\Api\Tenant;

use App\Http\Requests\Api\ApiFormRequest;

class UpdateTenantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $user = $this->user();
        $tenant = $this->route('tenant');

        if (!$user || !$tenant || !$user->hasPermissionKey('sys:tenant:edit')) {
            return false;
        }

        return $user->tenant_id
            && (int) $tenant->id === (int) $user->tenant_id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('expire_time') && !$this->has('expired_at')) {
            $this->merge(['expired_at' => $this->input('expire_time')]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')?->id;

        return [
            'name'          => 'sometimes|string',
            'code'          => "sometimes|string|unique:tenants,code,{$tenantId}",
            'status'        => 'nullable|in:0,1',
            'contact_name'  => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'expired_at'    => 'nullable|date',
            'remark'        => 'nullable|string',
        ];
    }
}
