<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAdditionalTaskRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'kepala_unit'; }

    public function rules(): array
    {
        return [
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'due_date'    => ['required','date'],
            'due_time'    => ['nullable','date_format:H:i'],
            'points'      => ['required','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // No additional validation.
    }
}
