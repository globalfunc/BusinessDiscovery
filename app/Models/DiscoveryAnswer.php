<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'discovery_session_id',
        'phase',
        'field_key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function discoverySession(): BelongsTo
    {
        return $this->belongsTo(DiscoverySession::class);
    }
}
