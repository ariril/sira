<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdditionalTaskRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'kepala_unit'; }

    public function rules(): array
    {
        return [
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'policy_doc'  => ['nullable','file','max:10240','mimes:pdf'],
            'due_date'    => ['required','date'],
            'due_time'    => ['nullable','date_format:H:i'],
            'points'      => ['required','numeric','min:0'],
            'max_claims'  => ['nullable','integer','min:1','max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tz = config('app.timezone');
            $today = \Illuminate\Support\Carbon::today($tz)->toDateString();
            $dueDate = (string) $this->input('due_date', '');
            if ($dueDate !== '' && $dueDate < $today) {
                $validator->errors()->add('due_date', 'Tanggal jatuh tempo tidak boleh sebelum hari ini.');
            }
        });
    }
}
