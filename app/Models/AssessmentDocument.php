<?php

namespace App\Models;

use Database\Factories\AssessmentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One versioned §6.4 internal technical assessment (admin-only, never shown
 * to the BO): tech stack, integrations, infra, implementation plan, effort
 * and pricing bands. Append-only like SpecDocument: assessment.generate or an
 * admin edit-save writes version+1 (generated_by ai|manual), never updates.
 * The latest version is what proposal.generate grounds its numbers in.
 */
class AssessmentDocument extends Model
{
    /** @use HasFactory<AssessmentDocumentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'business_owner_id',
        'version',
        'markdown',
        'generated_by',
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

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }
}
