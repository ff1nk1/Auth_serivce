<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogFilterRequest extends FormRequest
{
    /**
     * Разрешаем использование всем пользователям (это публичный каталог)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации для GET-параметров фильтра
     */
    public function rules(): array
    {
        return [
            'search'    => 'nullable|string|max:100',
            'min_price' => 'nullable|numeric|min:0',
            // max_price не может быть меньше min_price
            'max_price' => 'nullable|numeric|min:0|gte:min_price', 
            'sort'      => 'nullable|string|in:new,price_asc,price_desc',
        ];
    }
}