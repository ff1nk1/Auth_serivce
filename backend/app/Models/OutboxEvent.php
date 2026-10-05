<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'topic',
        'event_type',
        'payload',
    ];

    /**
     * Автоматически преобразуем JSON из базы в массив в PHP и обратно.
     */
    protected $casts = [
        'payload' => 'array',
    ];
}