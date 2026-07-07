<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'one_liner',
        'base_features',
        'saas_eligible',
        'tags',
        'price_min',
        'price_max',
        'hidden',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'one_liner' => 'array',
            'base_features' => 'array',
            'tags' => 'array',
            'saas_eligible' => 'boolean',
            'hidden' => 'boolean',
        ];
    }

    public function niches(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyNiche::class, 'service_niche')
            ->withPivot('recommended')
            ->withTimestamps();
    }
}
