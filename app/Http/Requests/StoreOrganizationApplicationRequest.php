<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrganizationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['custom_domain', 'domain_request'] as $field) {
            if ($this->filled($field)) {
                $this->merge([
                    $field => Str::lower(trim(preg_replace('#^https?://#', '', $this->string($field)->toString()), " \t\n\r\0\x0B/")),
                ]);
            }
        }
    }

    public function rules(): array
    {
        $centralDomains = array_filter(array_map('trim', explode(',', env('CENTRAL_DOMAINS', '127.0.0.1,localhost,'.env('LANDLORD_DOMAIN', 'localhost')))));

        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'domain_request' => ['nullable', 'string', 'max:255'],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/',
                Rule::notIn($centralDomains),
                'unique:tenant_applications,custom_domain',
                'unique:domains,domain',
            ],
            'business_type' => ['nullable', 'string', 'max:255'],
            'package_request' => ['nullable', 'string', 'max:255'],
            'module_request' => ['nullable', 'array'],
            'module_request.*' => ['string', 'exists:modules,slug'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'district' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:255'],
            'mikrotik_ip' => ['nullable', 'string', 'max:255'],
            'olt_ip' => ['nullable', 'string', 'max:255'],
            'olt_brand' => ['nullable', 'string', 'max:255'],
        ];
    }
}
