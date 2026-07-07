<?php

namespace App\Models;

use Database\Factories\DcpProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One versioned Discovery Context Profile (§3.1) per generation attempt.
 * Append-only: a retry writes a new row with version+1, never updates.
 * A failed/invalid generation stores an empty payload so downstream phases
 * can distinguish "AI ran and produced nothing usable" (offer retry, fall
 * back to static defaults) from "never generated" (no row at all).
 */
class DcpProfile extends Model
{
    /** @use HasFactory<DcpProfileFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'discovery_session_id',
        'payload',
        'version',
        'model_meta',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'model_meta' => 'array',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(DiscoverySession::class);
    }

    public function isEmpty(): bool
    {
        return $this->payload === null || $this->payload === [];
    }
}
