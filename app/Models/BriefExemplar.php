<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One hand-written gold pair for the S5.6 advisory-brief exemplar library:
 * a DCP-style context excerpt plus the brief we'd want the model to write
 * for it. Selected by tag relevance and injected as a §7.3-style context
 * block into suggest.content_social / suggest.growth calls. `version` bumps
 * when an exemplar is rewritten so persisted briefs stay reproducible
 * (advisory_briefs stores the id+version set that was in context).
 */
class BriefExemplar extends Model
{
    protected $fillable = [
        'context_tags',
        'dcp_excerpt',
        'exemplar_brief',
        'quality_notes',
        'active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'context_tags' => 'array',
            'exemplar_brief' => 'array',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
