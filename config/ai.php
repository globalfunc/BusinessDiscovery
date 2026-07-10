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
    | Applied to every AiClient call unless a tool overrides them below, or an
    | admin overrides either via the S4.7 AI settings screen — AiSettings
    | reads a `settings` row first and falls back to these values, so an
    | untouched deployment behaves exactly as before.
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

        // S4.5 admin-side generators (§6.4/§6.5). vendor_filter => false is
        // the §7.6.4 scope rule: these are admin-authored drafts with a
        // mandatory human edit pass before anything reaches the BO (the
        // assessment never reaches the BO at all), and their prompts actively
        // invite naming real platforms/vendors for comparison — so neither
        // VendorPolicy::systemRule() nor the AiClient output scan applies.
        // Every other tool omits the key and stays filtered by default.
        'assessment.generate' => [
            'max_tokens' => 8192,
            'vendor_filter' => false,
        ],
        'proposal.generate' => [
            'max_tokens' => 8192,
            'vendor_filter' => false,
        ],
        'email.generate' => [
            'max_tokens' => 2048,
            'vendor_filter' => false,
        ],

        // S5.7 LLM-as-judge over advisory briefs: cheap model, tight cap —
        // the output is four scores plus one-line reasons, nothing more.
        // Vendor filter stays on (default): the reasons are prose an admin
        // reads, so they follow the same neutrality rule as everything else.
        'brief.grade' => [
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1024,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing table ($ per million tokens), for cost_estimate on ai_calls
    |--------------------------------------------------------------------------
    |
    | S3.6 (token budgeting) and S4.7 (AI & system settings / usage explorer)
    | read this table via AiSettings::pricing() to compute and display cost.
    | Keep it in sync with platform.claude.com/docs/en/pricing — it's the
    | fallback under any admin-edited "price table" override (§6.7).
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

    // §6.7 usage-explorer alert threshold: % of a cap consumed before the
    // admin UI flags a BO/the global total as "nearing budget." Advisory
    // only — does not affect BudgetGate's hard/soft enforcement below.
    'alert_threshold_pct' => env('AI_ALERT_THRESHOLD_PCT', 80),

    // 'hard' blocks the call once a cap is reached (routes to each tool's
    // existing fallback, §7.7); 'soft' only warns (logs) and lets calls
    // through — useful while calibrating caps against real usage.
    'budget_mode' => env('AI_BUDGET_MODE', 'hard'),

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

    /*
    |--------------------------------------------------------------------------
    | Advisory-brief grading rubric (S5.7)
    |--------------------------------------------------------------------------
    |
    | Defaults under the admin-edited rubric (AiSettings::briefRubric()), same
    | fallback pattern as everything above. Weights are relative (normalized
    | at scoring time); the composite is the weighted mean of the 1–5
    | per-dimension scores. `mode` log_only grades + persists but reveals
    | whatever passed the S5.6 deterministic gate; `enforce` hides briefs
    | whose composite misses `threshold` (verdict hidden_low_value). Ships
    | log_only — flip to enforce once the threshold is calibrated against
    | admin labels. `version` is auto-bumped by the rubric editor when the
    | dimensions change, and stamped on every graded advisory_briefs row.
    */

    'brief_rubric' => [
        'version' => 1,
        'mode' => 'log_only',
        'threshold' => 3.5,
        'dimensions' => [
            [
                'key' => 'specificity',
                'label' => 'Specificity',
                'description' => 'References this owner\'s real business — their niche, stated pain points, or goals — not advice any business could receive.',
                'weight' => 0.3,
            ],
            [
                'key' => 'insight',
                'label' => 'Insight vs. platitude',
                'description' => 'Offers a genuine observation or non-obvious direction rather than generic filler ("post consistently", "engage your audience").',
                'weight' => 0.3,
            ],
            [
                'key' => 'non_deliverable',
                'label' => 'Non-deliverable / safety',
                'description' => 'Stays general advice and insight; does not drift into ready-to-publish copy, captions, scripts, or a step-by-step action plan.',
                'weight' => 0.2,
            ],
            [
                'key' => 'credibility',
                'label' => 'Credibility / tone',
                'description' => 'Sounds like a seasoned studio advisor: professional, warm, concrete; no hype, no scolding, no invented facts about the business.',
                'weight' => 0.2,
            ],
        ],
    ],

];
