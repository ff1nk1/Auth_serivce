<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id', // Добавлено для связи
        'first_name',
        'last_name',
        'middle_name',
        'avatar_url',
        'birthday',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    // Обратная связь с пользователем
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}