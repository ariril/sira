<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelAdditionalTaskClaimRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'pegawai_medis'; }

    public function rules(): array
    {
        return [
            'reason' => ['nullable','string','max:500'],
        ];
    }
}
