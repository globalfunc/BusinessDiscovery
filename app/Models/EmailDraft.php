<?php

namespace App\Models;

use App\Enums\EmailKind;
use Database\Factories\EmailDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated §6.5 email draft (Warm tease / Follow-up / Proposal cover,
 * BG/EN). Append-only history so the admin can revisit earlier copy; the UI
 * is copy-to-clipboard only — no sending in v1 (non-goal).
 */
class EmailDraft extends Model
{
    /** @use HasFactory<EmailDraftFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'business_owner_id',
        'kind',
        'language',
        'subject',
        'body',
        'model_meta',
    ];

    protected function casts(): array
    {
        return [
            'kind' => EmailKind::class,
            'model_meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }
}
