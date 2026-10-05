<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Авторизация: здесь можно проверить права (например, только админ может создавать товары)
     */
    public function authorize(): bool
    {
        return true; // Временно разрешаем всем, пока нет системы ролей
    }

    /**
     * Правила валидации для создания товара (POST /products)
     */
    public function rules(): array
    {
        return [
            'store_id'    => 'required|integer|exists:stores,id',
            'category_id' => 'required|integer|exists:categories,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url'   => 'nullable|url|max:255',
            'price'       => 'required|numeric|min:0',
        ];
    }
}