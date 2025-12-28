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
            'start_date'  => ['required','date'],
            'start_time'  => ['nullable','date_format:H:i'],
            'due_date'    => ['required','date','after_or_equal:start_date'],
            'due_time'    => ['nullable','date_format:H:i'],
            'bonus_amount'=> ['nullable','numeric','min:0'],
            'points'      => ['nullable','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
            'cancel_window_hours' => ['nullable','integer','min:0','max:720'],
            'default_penalty_type' => ['nullable','string','in:none,percent,amount'],
            'default_penalty_value' => ['nullable','numeric','min:0'],
            'penalty_base' => ['nullable','string','in:task_bonus,remuneration'],
            'supporting_file' => ['nullable','file','max:10240','mimes:doc,docx,xls,xlsx,ppt,pptx,pdf'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasBonus = $this->filled('bonus_amount');
            $hasPoints = $this->filled('points');

            if (!$hasBonus && !$hasPoints) {
                $validator->errors()->add('bonus_amount', 'Isi Bonus atau Poin minimal salah satu.');
                $validator->errors()->add('points', 'Isi Bonus atau Poin minimal salah satu.');
            }

            if ($hasBonus && $hasPoints) {
                $validator->errors()->add('bonus_amount', 'Pilih salah satu antara Bonus atau Poin.');
                $validator->errors()->add('points', 'Pilih salah satu antara Bonus atau Poin.');
            }

            $penaltyType = (string) $this->input('default_penalty_type', 'none');
            if ($penaltyType === 'percent') {
                $penaltyValue = (float) $this->input('default_penalty_value', 0);
                if ($penaltyValue > 100) {
                    $validator->errors()->add('default_penalty_value', 'Penalty percent harus di antara 0–100.');
                }
            }
        });
    }
}
