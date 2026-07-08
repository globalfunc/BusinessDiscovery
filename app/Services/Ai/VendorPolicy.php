<?php

namespace App\Services\Ai;

/**
 * Single source of truth for the §7.6.1 vendor-neutrality *prompt* policy.
 *
 * Every tool's PromptTemplate embeds {@see self::systemRule()} as the first
 * rule in its SystemPolicy block (§7.3 block 1) instead of restating the
 * vendor mandate inline — change the wording here and all five live tools
 * (dcp.generate + the four suggest.*) inherit it. The *runtime* half of §7.6
 * (post-generation scan, single regeneration, redact + vendor_leak logging)
 * lives in {@see VendorFilter}, which reuses {@see self::correctiveInstruction()}
 * for its regeneration turn.
 */
final class VendorPolicy
{
    /**
     * The canonical §7.6.1 mandate, phrased to cover every text-emitting tool:
     * software capabilities, channels/platforms, and brand/design/typography
     * references alike. Written as a single rule sentence so templates can drop
     * it in as one bullet.
     */
    public const SYSTEM_RULE = 'Never name, recommend, or allude to real third-party products, vendors, platforms, brands, tools, named design systems, or fonts owned by other companies — even if the owner named one first. Every solution is a custom service we deliver; describe everything by capability only (e.g. "online booking", "a short-video platform", "bold display typography"), never by brand name.';

    public static function systemRule(): string
    {
        return self::SYSTEM_RULE;
    }

    /**
     * The corrective user turn appended on the single regeneration when the
     * first response tripped the blocklist (§7.6.2). Names the offending terms
     * so the model knows exactly what to strip, and pins the output format so
     * a regenerated JSON tool still returns parseable JSON.
     *
     * @param  string[]  $terms  the leaked brand/vendor strings that were matched
     */
    public static function correctiveInstruction(array $terms): string
    {
        $named = implode(', ', array_map(fn ($t) => '"'.$t.'"', array_values(array_unique($terms))));

        return <<<TXT
        Your previous response named or alluded to real third-party brand(s)/vendor(s): {$named}. This breaks our hard vendor-neutrality rule.

        Rewrite your entire previous response, removing every real product, vendor, platform, brand, tool, design-system, or font name. Describe each only by its capability (e.g. "online booking", not a brand). Keep the exact same format, structure, and language as before — if the previous response was JSON, return the same JSON shape with the same keys. Output only the corrected response, nothing else.
        TXT;
    }
}
