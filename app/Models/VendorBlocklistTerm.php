<?php

namespace App\Models;

use App\Services\Ai\VendorFilter;
use Database\Factories\VendorBlocklistTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One entry in the §7.6.2 vendor blocklist. Loaded (and cached) by
 * {@see VendorFilter}; any write flushes that cache so the
 * output filter always scans against the current list.
 */
class VendorBlocklistTerm extends Model
{
    /** @use HasFactory<VendorBlocklistTermFactory> */
    use HasFactory;

    /** Cache key the VendorFilter reads active terms from. */
    public const CACHE_KEY = 'vendor_blocklist_terms.active';

    protected $fillable = [
        'term',
        'is_regex',
        'replacement',
        'active',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'is_regex' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }
}
