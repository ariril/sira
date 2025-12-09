<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAdditionalTaskClaimRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'kepala_unit'; }

    public function rules(): array
    {
        return [
            'action' => ['required','string','in:validate,approve,reject'],
            'comment' => ['nullable','string','max:2000'],
        ];
    }
}
