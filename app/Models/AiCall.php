<?php

namespace App\Models;

use App\Enums\AiCallStatus;
use Database\Factories\AiCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCall extends Model
{
    /** @use HasFactory<AiCallFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'business_owner_id',
        'tool',
        'model',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'cost_estimate',
        'status',
        'vendor_leak',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiCallStatus::class,
            'vendor_leak' => 'boolean',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'cost_estimate' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }
}
