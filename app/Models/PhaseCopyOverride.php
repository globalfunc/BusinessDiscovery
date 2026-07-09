<?php

namespace App\Models;

use App\Enums\DiscoveryPhase;
use Database\Factories\PhaseCopyOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable override for a phase's title/helper text, or (phase =
 * "greeting") the default greeting template's title/body (§6.6). Merged
 * over the static bg/en lang JSON at runtime — see HandleInertiaRequests
 * and resources/js/lib/i18n.ts. A row with all fields null is equivalent
 * to no override; the admin editor deletes such rows on save.
 */
class PhaseCopyOverride extends Model
{
    /** @use HasFactory<PhaseCopyOverrideFactory> */
    use HasFactory;

    public const GREETING = 'greeting';

    /**
     * @return list<string>
     */
    public static function phases(): array
    {
        return [
            ...array_map(fn ($phase) => $phase->value, DiscoveryPhase::ordered()),
            self::GREETING,
        ];
    }

    protected $fillable = [
        'phase',
        'language',
        'title',
        'helper',
        'body',
    ];
}
