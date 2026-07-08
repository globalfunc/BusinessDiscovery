<?php

namespace App\Models;

use Database\Factories\SuggestionPresetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Static Suggestion Cards for one niche + phase (§6.6). Doubles as prompt
 * inspiration (§7.3 block 7) and as the AI-unavailable fallback (§7.7).
 * `cards` is an array of §7.4-shaped card objects; the admin editor is S4.7.
 */
class SuggestionPreset extends Model
{
    /** @use HasFactory<SuggestionPresetFactory> */
    use HasFactory;

    protected $fillable = [
        'taxonomy_niche_id',
        'phase',
        'cards',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNiche::class, 'taxonomy_niche_id');
    }
}
