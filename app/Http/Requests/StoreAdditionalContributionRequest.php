<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdditionalContributionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'pegawai_medis'; }

    public function rules(): array
    {
        return [
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:2000'],
            'claim_id'    => ['nullable','integer','exists:additional_task_claims,id'],
            'file'        => ['nullable','file','max:20480','mimes:pdf,xls,xlsx,doc,docx,ppt,pptx,zip,jpg,jpeg,png'],
        ];
    }
}
