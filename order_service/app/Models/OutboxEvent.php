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

    protected $casts = [
        'payload' => 'array',
    ];
}
