<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ];
    }
}
