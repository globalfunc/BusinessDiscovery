<?php

namespace App\Models;

use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStage extends Model
{
    /** @use HasFactory<\Database\Factories\LeadStageFactory> */
    use HasFactory;

    protected $fillable = [
        'business_owner_id',
        'stage',
        'note',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => PipelineStage::class,
            'changed_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
