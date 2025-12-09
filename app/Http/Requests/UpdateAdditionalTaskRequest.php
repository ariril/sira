<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdditionalTaskRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'kepala_unit'; }

    public function rules(): array
    {
        return [
            'assessment_period_id' => ['nullable','integer','exists:assessment_periods,id'],
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'start_date'  => ['required','date'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'bonus_amount'=> ['nullable','numeric','min:0','max:999999999'],
            'points'      => ['nullable','numeric','min:0','max:999999'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'status'      => ['nullable','in:draft,open,closed,cancelled'],
        ];
    }
}
