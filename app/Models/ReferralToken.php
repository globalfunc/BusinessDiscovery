<?php

namespace App\Models;

use App\Enums\ReferralTokenState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralToken extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralTokenFactory> */
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
}
