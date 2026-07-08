<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API credentials
    |--------------------------------------------------------------------------
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default call parameters
    |--------------------------------------------------------------------------
    |
    | Applied to every AiClient call unless a tool overrides them below.
    | Admin-configurable model/effort/temperature screens land in S4.8; until
    | then these are the only knobs, set via .env per deployment.
    |
    */

    'default_model' => env('AI_DEFAULT_MODEL', 'claude-sonnet-5'),
    'default_effort' => env('AI_DEFAULT_EFFORT', 'medium'),
    'default_max_tokens' => env('AI_DEFAULT_MAX_TOKENS', 4096),
    'default_temperature' => env('AI_DEFAULT_TEMPERATURE'), // null = omit (adaptive-thinking models reject non-default temperature)
    'request_timeout' => env('AI_REQUEST_TIMEOUT', 25), // seconds; §7.1 synchronous suggestion-call budget

    /*
    |--------------------------------------------------------------------------
    | Per-tool overrides
    |--------------------------------------------------------------------------
    |
    | Keyed by tool name (§7.2: dcp.generate, suggest.services, suggest.branding,
    | suggest.content_social, suggest.growth, spec.compile, spec.amend,
    | proposal.generate, assessment.generate, email.generate). Concrete tools
    | are added by S3.1+; any key omitted here falls back to the defaults above.
    */

    'tools' => [
        // Schema-constrained JSON extraction on Phase 0 continue (§3.1); the
        // BO waits on this synchronously, so the output stays tightly capped.
        'dcp.generate' => [
            'max_tokens' => 2048,
        ],

        // Synchronous ✨ suggestion calls (§7.1): 3–5 feature-rich cards, so
        // a little more room than the DCP but still capped for the 25s budget.
        'suggest.services' => [
            'max_tokens' => 3072,
        ],
        'suggest.branding' => [
            'max_tokens' => 3072,
        ],
        'suggest.content_social' => [
            'max_tokens' => 3072,
        ],
        'suggest.growth' => [
            'max_tokens' => 3072,
        ],

        // Full 10-section document generation (§7.5) — much longer output
        // than the card tools; amend carries the whole revised document
        // inside a JSON envelope, so it gets the same headroom.
        'spec.compile' => [
            'max_tokens' => 8192,
        ],
        'spec.amend' => [
            'max_tokens' => 8192,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing table ($ per million tokens), for cost_estimate on ai_calls
    |--------------------------------------------------------------------------
    |
    | S3.6 (token budgeting) and S4.8 (AI & system settings / usage explorer)
    | read this table to compute and display cost. Keep it in sync with
    | platform.claude.com/docs/en/pricing — update here first if the admin
    | "price table" setting (§6.7) doesn't yet override it.
    */

    'pricing' => [
        'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-8' => ['input' => 5.00, 'output' => 25.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token budgets (§7.7, §0.1)
    |--------------------------------------------------------------------------
    |
    | Values only — enforcement (pre-flight check, hard-stop vs soft-warn,
    | rate limiting) is built in S3.6. Per-BO cap is admin-overridable there
    | via a column on business_owners; this is just the global default.
    */

    'per_bo_token_cap' => env('AI_PER_BO_TOKEN_CAP', 300000),
    'global_monthly_token_cap' => env('AI_GLOBAL_MONTHLY_TOKEN_CAP'),
    'rate_limit_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 6),

    /*
    |--------------------------------------------------------------------------
    | Vendor-neutrality redaction (§7.6.2)
    |--------------------------------------------------------------------------
    |
    | Fallback generic label the output filter swaps a leaked brand/vendor term
    | for when a blocklist row has no per-term replacement of its own. Used only
    | on the second hit (after the single regeneration), which also flags the
    | ai_calls.vendor_leak column for admin review.
    */

    'vendor_redaction_label' => env('AI_VENDOR_REDACTION_LABEL', 'a custom solution'),

];
