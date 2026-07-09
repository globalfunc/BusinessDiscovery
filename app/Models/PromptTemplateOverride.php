<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A saved version of a tool's system prompt (§6.7 prompt template
 * viewer/editor). See PromptTemplateRegistry for how the active row (if any)
 * takes precedence over the hardcoded PromptTemplate class.
 */
class PromptTemplateOverride extends Model
{
    protected $fillable = [
        'tool',
        'version',
        'system_prompt',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'active' => 'boolean',
        ];
    }
}
