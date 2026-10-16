<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCatalog extends Model
{
    protected $table = 'product_catalog';

    protected $fillable = [
        'product_id',
        'store_id',
        'attributes',
        'is_published',
        'embedding',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_published' => 'boolean',
        'product_id' => 'integer',
        'store_id' => 'integer',
    ];
}
