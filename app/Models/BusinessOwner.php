<?php

namespace App\Models;

use App\Enums\BusinessOwnerStatus;
use App\Enums\Language;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessOwner extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessOwnerFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'company',
        'logo_path',
        'greeting_override',
        'language',
        'admin_context',
        'pre_selected_niche_id',
        'status',
        'current_stage',
    ];

    protected function casts(): array
    {
        return [
            'language' => Language::class,
            'status' => BusinessOwnerStatus::class,
            'current_stage' => PipelineStage::class,
        ];
    }

    public function preSelectedNiche(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNiche::class, 'pre_selected_niche_id');
    }

    public function referralTokens(): HasMany
    {
        return $this->hasMany(ReferralToken::class);
    }

    public function leadStages(): HasMany
    {
        return $this->hasMany(LeadStage::class);
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    public function discoverySession(): HasOne
    {
        return $this->hasOne(DiscoverySession::class);
    }
}
