<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mikrotik_id' => 'required|exists:mikrotiks,id',
            'zone_id' => 'nullable|exists:zones,id',
            'sub_zone_id' => 'nullable|exists:sub_zones,id',
            'olt_id' => 'nullable|exists:olts,id',
            'onu_mac' => 'nullable|string|max:255',
            'onu_serial' => 'nullable|string|max:255',
            'pon_port' => 'nullable|string|max:255',
            'pppoe_username' => 'required|string|unique:clients,pppoe_username',
            'pppoe_password' => 'required|string|min:6',
            'package_name' => 'required|string',
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'phone_number' => 'required|string',
            'telegram_chat_id' => 'nullable|string',
            'monthly_bill' => 'required|numeric',
            'full_address' => 'required|string',
            'expiry_date' => 'required|date',
            'additional_notes' => 'nullable|string',
        ];
    }
}
