<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Conversation extends Model
{
    use HasUuids;

    public const HARD_TTL_HOURS = 24;

    public const IDLE_TTL_MINUTES = 30;

    protected $fillable = [
        'user_id',
        'last_activity_at',
        'expires_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public static function computeExpiresAt(Carbon $createdAt, Carbon $lastActivityAt): Carbon
    {
        $hardCap = $createdAt->copy()->addHours(self::HARD_TTL_HOURS);
        $idleCap = $lastActivityAt->copy()->addMinutes(self::IDLE_TTL_MINUTES);

        return $hardCap->lt($idleCap) ? $hardCap : $idleCap;
    }

    public function touchActivity(?Carbon $at = null): void
    {
        $at ??= now();
        $this->last_activity_at = $at;
        $this->expires_at = self::computeExpiresAt($this->created_at ?? $at, $at);
        $this->save();
    }
}
