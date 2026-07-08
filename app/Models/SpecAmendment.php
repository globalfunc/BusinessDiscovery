<?php

namespace App\Models;

use Database\Factories\SpecAmendmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BO amendment instruction (§3.8 amend loop): applied against the
 * spec_document it belongs to, producing the session's spec version
 * `resulting_version`. Only successful amendments are recorded — a failed
 * spec.amend call leaves no row and no new spec version.
 */
class SpecAmendment extends Model
{
    /** @use HasFactory<SpecAmendmentFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'spec_document_id',
        'instruction',
        'resulting_version',
    ];

    protected function casts(): array
    {
        return [
            'resulting_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function specDocument(): BelongsTo
    {
        return $this->belongsTo(SpecDocument::class);
    }
}
