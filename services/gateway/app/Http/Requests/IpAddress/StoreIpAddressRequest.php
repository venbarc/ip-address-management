<?php

namespace App\Http\Requests\IpAddress;

use Illuminate\Foundation\Http\FormRequest;

class StoreIpAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'ip'],
            'label' => ['required', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
