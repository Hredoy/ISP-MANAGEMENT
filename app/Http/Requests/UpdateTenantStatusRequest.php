<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_INACTIVE,
                Tenant::STATUS_SUSPENDED,
                Tenant::STATUS_PENDING_SETUP,
            ])],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
