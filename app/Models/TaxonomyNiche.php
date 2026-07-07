<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxonomyNiche extends Model
{
    /** @use HasFactory<\Database\Factories\TaxonomyNicheFactory> */
    use HasFactory;

    protected $fillable = [
        'taxonomy_category_id',
        'name',
        'sort',
        'hidden',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'hidden' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaxonomyCategory::class, 'taxonomy_category_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_niche')
            ->withPivot('recommended')
            ->withTimestamps();
    }

    public function businessOwners(): HasMany
    {
        return $this->hasMany(BusinessOwner::class, 'pre_selected_niche_id');
    }
}
