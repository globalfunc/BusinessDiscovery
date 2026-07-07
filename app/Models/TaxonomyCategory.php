<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxonomyCategory extends Model
{
    /** @use HasFactory<\Database\Factories\TaxonomyCategoryFactory> */
    use HasFactory;

    protected $fillable = [
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

    public function niches(): HasMany
    {
        return $this->hasMany(TaxonomyNiche::class);
    }
}
