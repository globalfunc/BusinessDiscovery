<?php

namespace App\Models;

use Database\Factories\SpecDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One versioned §7.5 Business Specification (10-section markdown), produced
 * by spec.compile / spec.amend or by the deterministic fallback renderer
 * (generated_by ai|fallback). Append-only like DcpProfile: a regenerate or
 * amend writes version+1, never updates — version history is the audit trail
 * the admin diff view (S4.4) reads. model_meta carries the §7.5 generation
 * metadata (model, prompt version, ai_call_id, language, status).
 */
class SpecDocument extends Model
{
    /** @use HasFactory<SpecDocumentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'discovery_session_id',
        'version',
        'markdown',
        'generated_by',
        'change_summary',
        'model_meta',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'model_meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(DiscoverySession::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(SpecAmendment::class);
    }

    /** @return array<string, mixed> Shape shared with the Review screen. */
    public function toDiscoveryArray(): array
    {
        return [
            'version' => $this->version,
            'markdown' => $this->markdown,
            'generated_by' => $this->generated_by,
            'change_summary' => $this->change_summary,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
