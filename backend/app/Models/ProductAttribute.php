<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    use HasFactory;

    /**
     * Поля, доступные для массового заполнения (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'value',
    ];

    /**
     * Товар, которому принадлежит атрибут.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}