<?php

namespace App\Models;

use App\Enums\AdvisoryBriefVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One S5.6 advisory brief ("note from the studio") per generation attempt,
 * append-only: regenerating suggestions writes a new row, and the latest row
 * per (business_owner, phase, module) supersedes older ones. Every row keeps
 * the reproducibility metadata (model, prompt version, exemplar id+version
 * set, DCP snapshot reference) so any brief — shown or dropped — can be traced
 * back to exactly what produced it. S5.7 hangs judge scores off these rows.
 */
class AdvisoryBrief extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'business_owner_id',
        'phase',
        'module',
        'brief',
        'verdict',
        'drop_reason',
        'model',
        'prompt_version',
        'exemplars',
        'dcp_profile_id',
    ];

    protected function casts(): array
    {
        return [
            'brief' => 'array',
            'exemplars' => 'array',
            'verdict' => AdvisoryBriefVerdict::class,
            'prompt_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function dcpProfile(): BelongsTo
    {
        return $this->belongsTo(DcpProfile::class);
    }

    /** The superseding row for one (BO, phase, module) touchpoint. */
    public function scopeLatestFor(Builder $query, int $businessOwnerId, string $phase, ?string $module): Builder
    {
        return $query
            ->where('business_owner_id', $businessOwnerId)
            ->where('phase', $phase)
            ->when(
                $module === null,
                fn (Builder $q) => $q->whereNull('module'),
                fn (Builder $q) => $q->where('module', $module),
            )
            ->orderByDesc('id');
    }
}
