<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAdditionalTaskClaimResultRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === 'pegawai_medis'; }

    public function rules(): array
    {
        return [
            'note' => ['nullable','string','max:1000'],
            'result_file' => ['required','file','max:10240','mimes:doc,docx,xls,xlsx,ppt,pptx,pdf'],
        ];
    }
}
