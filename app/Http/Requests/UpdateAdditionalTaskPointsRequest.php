<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdditionalTaskPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'kepala_unit';
    }

    public function rules(): array
    {
        return [
            'points' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
