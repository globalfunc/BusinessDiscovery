<?php

namespace App\Models;

use App\Enums\DiscoveryPhase;
use App\Enums\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscoverySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_owner_id',
        'current_phase',
        'language',
        'status',
        'started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'current_phase' => DiscoveryPhase::class,
            'language' => Language::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(BusinessOwner::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DiscoveryAnswer::class);
    }

    public function selectedServices(): HasMany
    {
        return $this->hasMany(SelectedService::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function dcpProfiles(): HasMany
    {
        return $this->hasMany(DcpProfile::class);
    }

    public function latestDcpProfile(): HasOne
    {
        return $this->hasOne(DcpProfile::class)->ofMany('version', 'max');
    }

    public function specDocuments(): HasMany
    {
        return $this->hasMany(SpecDocument::class);
    }

    public function latestSpecDocument(): HasOne
    {
        return $this->hasOne(SpecDocument::class)->ofMany('version', 'max');
    }
}
