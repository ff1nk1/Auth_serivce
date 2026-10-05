<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // sometimes означает, что правило применяется только если поле передано в запросе
            'store_id'    => 'sometimes|integer|exists:stores,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image_url'   => 'nullable|url|max:255',
            'price'       => 'sometimes|numeric|min:0',
        ];
    }
}