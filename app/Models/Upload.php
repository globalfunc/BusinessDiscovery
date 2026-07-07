<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_owner_id',
        'discovery_session_id',
        'phase',
        'path',
        'thumb_path',
        'original_name',
        'mime',
        'size',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(DiscoverySession::class);
    }

    public function toDiscoveryArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'kind' => $this->kind,
            'url' => URL::temporarySignedRoute('uploads.show', now()->addHours(2), ['upload' => $this->id]),
            'thumbnail_url' => $this->thumb_path
                ? URL::temporarySignedRoute('uploads.show', now()->addHours(2), ['upload' => $this->id, 'thumb' => 1])
                : null,
        ];
    }
}
