<?php

namespace App\Http\Requests\Api\Tenant;

use App\Http\Requests\Api\ApiFormRequest;

class StoreTenantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('expire_time') && !$this->has('expired_at')) {
            $this->merge(['expired_at' => $this->input('expire_time')]);
        }
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string',
            'code'          => 'required|string|unique:tenants,code',
            'status'        => 'nullable|in:0,1',
            'contact_name'  => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'expired_at'    => 'nullable|date',
            'remark'        => 'nullable|string',
        ];
    }
}
