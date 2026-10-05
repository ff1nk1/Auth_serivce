<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Получаем текущую категорию из роута, чтобы игнорировать её собственный slug при проверке на уникальность
        $categoryId = $this->route('category')->id;

        return [
            'name'      => 'sometimes|required|string|max:255',
            'slug'      => 'sometimes|required|string|unique:categories,slug,' . $categoryId,
            'parent_id' => 'nullable|exists:categories,id'
        ];
    }
}