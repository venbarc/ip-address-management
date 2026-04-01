<?php

namespace App\Http\Requests\IpAddress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIpAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
