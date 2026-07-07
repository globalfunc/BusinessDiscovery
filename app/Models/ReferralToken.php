<?php

namespace App\Models;

use App\Enums\ReferralTokenState;
use Database\Factories\ReferralTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReferralToken extends Model
{
    /** @use HasFactory<ReferralTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'business_owner_id',
        'token_hash',
        'expires_at',
        'state',
        'sent_at',
        'first_visited_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => ReferralTokenState::class,
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'first_visited_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    /**
     * Create a new token for a BO, returning [model, plaintext token].
     * The plaintext is never persisted — only its hash is stored.
     */
    public static function generateFor(BusinessOwner $businessOwner, int $expiryDays = 30): array
    {
        $plain = Str::random(40);

        $token = static::create([
            'business_owner_id' => $businessOwner->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays($expiryDays),
            'state' => ReferralTokenState::Created,
        ]);

        return [$token, $plain];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->state !== ReferralTokenState::Revoked
            && $this->state !== ReferralTokenState::Expired
            && ! $this->isExpired();
    }
}
