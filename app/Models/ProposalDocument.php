<?php

namespace App\Models;

use Database\Factories\ProposalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One versioned §6.4 client-facing proposal. Append-only like SpecDocument:
 * proposal.generate (grounded in the latest assessment), an admin edit-save,
 * or an externally-written upload each write version+1 (generated_by
 * ai|manual|uploaded). Uploaded versions carry the file via upload_id and a
 * null markdown; attachments holds upload ids pinned to this version.
 */
class ProposalDocument extends Model
{
    /** @use HasFactory<ProposalDocumentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'business_owner_id',
        'version',
        'markdown',
        'generated_by',
        'upload_id',
        'attachments',
        'model_meta',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'attachments' => 'array',
            'model_meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
