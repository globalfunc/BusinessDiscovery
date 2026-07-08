# BusinessDiscovery — Phase-by-Phase Implementation Plan (Session-Based)
**Companion to:** `Technical_Specification.md`, `design.md`
**Purpose:** Break the build into discrete Claude Code sessions, each scoped to comfortably fit within a single ~200k-token context window, with an explicit model assignment per session.

---

## 0.1 Anchored decisions (resolves Technical_Specification.md §12 open questions)

| Question | Decision |
|---|---|
| App/brand name | **BusinessDiscovery** — replaces the "LaunchBrief" placeholder everywhere (repo, UI, docs). |
| BO price visibility | **Show approximate prices**, admin-configurable. Per-service indicative price/range (catalog + custom services), summed into an approx. total project price. Global **admin toggle** to show/hide prices and the approx. total — off by default is not assumed, admin sets it. |
| Pricing granularity | **Per-service price/range**, not just billing-model bands — matches the feature-level detail used everywhere else in the spec. |
| Admin portal language | **English only**, no i18n toggle in admin (single-operator tool, keeps build scope down). |
| BO flow language default | **Auto-detect via browser `Accept-Language` header** (BG if reported, else EN) — no IP geolocation service, no external dependency, no added privacy/GDPR surface. BO can always override via the language toggle; choice persists. |
| Default currency | **EUR default**, BGN as the toggle option (reflects Bulgaria's euro transition). |
| Per-BO AI token budget | **300k tokens/BO** default, admin-overridable per-BO and globally. |
| Admin 2FA (TOTP) | **Deferred** — password-only admin auth in v1; revisit in v2. |
| Proposal PDF export | **Stage 5** (polish), not bundled with the Stage 4 proposal generator — matches the spec's "nice-to-have, can slip" note. |

These decisions are reflected in the session scopes below; where a session's description changed as a result, it's called out inline.

## 0.2 Scope addition — public landing page

Technical_Specification.md §1.3 lists "public signup, marketing site, or SEO" as a v1 non-goal. This is refined, not overridden: **BusinessDiscovery gets a public homepage that markets the app and captures a referral *request*, but it grants no access.** The closed, referral-only model (§1.1/§2) is preserved — the landing page's CTA submits a lead-capture form (name, company, email, phone required; current website/social link optional) into a new admin review queue. The admin still manually creates the BO and issues the referral link (reusing the existing S1.3 flow); nothing on the public page auto-provisions access. See **Stage 6** below.

---

## 0. How to use this plan

- Each session below is a **unit of work for one sitting**: one scoped slice of the codebase, ending in a committable, working increment (per §11 of the tech spec: "each stage ends with a working, deployable increment").
- **Do not merge sessions** to save time — the 200k-token ceiling is a hard planning constraint (Pro-plan session limits), and undersized sessions leave headroom for review/iteration/test-fixing within the same session.
- Sessions are numbered `S<stage>.<n>` and listed in dependency order within a stage. Cross-stage dependencies are called out explicitly.
- At the start of each session, re-read `Technical_Specification.md` (relevant section only) and `design.md` (relevant component section only) rather than the whole document, to conserve context.
- End each session by leaving a short handoff note (in the PR description or a `NOTES.md` scratch file) covering: what changed, what's stubbed, what the next session needs to know. Keep it under ~15 lines — this is what keeps the *next* session's context small, not a full recap.

### Model assignment legend
| Tag | Model | Effort | Notes |
|---|---|---|---|
| **[Sonnet]** | Current model (Sonnet 5) | Medium | Default for scaffolding, CRUD, UI plumbing, admin screens, infra, tests. |
| **[Fable · AI-flow]** | Fable 5 | Medium | Reserved for the AI-driven discovery phases and the onboarding/intake interview stage specifically — these are the sessions where conversational/adaptive AI UX quality matters most. |
| **[Fable · complex]** | Fable 5 | Medium | Other tasks flagged as high-complexity (prompt/schema design, vendor-safety filtering, generative document assembly) — assigned to Fable 5 even though they aren't part of the core discovery interview. |

**Agent-spawning rule (applies to every session, strictest on Fable sessions):** max **2 parallel subagents** at any time. The user is on a Pro plan, not Max — do not fan out broad Explore/general-purpose swarms. Prefer direct tool use (Read/Grep/Bash) over spawning a subagent for anything you can answer in 1–3 direct lookups; reserve subagents for genuinely independent, parallelizable chunks of a session (e.g., "write migration A" + "write migration B" concurrently), never for exploration you could do yourself in fewer steps.

---

## Stage 0 — Repo & Environment Bootstrap

### S0.1 — Project scaffold **[Sonnet]**
- Laravel 11 + PHP 8.3 project init; Inertia.js + React 18 + TypeScript + Vite; Tailwind CSS configured with the `design.md` §2/§3 tokens as CSS variables and Tailwind theme extensions; shadcn/ui-style component primitives installed (button, card, dialog, input, toast, etc.) restyled to the dark charcoal/gold palette.
- Base app shell: root layout, font loading (Fraunces / Space Grotesk / Inter via self-hosted or Google Fonts), global CSS reset, `prefers-reduced-motion` handling.
- Ziggy route helper, ESLint/Prettier/PHP-CS-Fixer, `.env.example`, minimal `README.md` (setup steps only).
- **Deliverable:** blank app boots, renders a themed placeholder page proving the token system + fonts + dark base work.

---

## Stage 1 — Foundation

### S1.1 — Core schema migrations **[Sonnet]**
- Migrations for: `users`, `business_owners`, `referral_tokens`, `lead_stages`, `taxonomy_categories`, `taxonomy_niches`, `services`, `service_niche`, `settings`, `activity_events` (§8). No AI-related tables yet (deferred to Stage 3 sessions that need them).
- Model classes + factories for seeding/testing.

### S1.2 — Admin auth & admin shell **[Sonnet]**
- Laravel Breeze/Fortify admin auth (email+password only — TOTP 2FA deferred to v2 per §0.1), single seeded admin account.
- Admin layout shell: left nav rail, hero-band + two-column grid content area per `design.md` §4.2/§6.8 (empty/placeholder widgets is fine here — real data comes in Stage 4). English copy only, no i18n scaffolding needed on the admin side.

### S1.3 — BO & referral link lifecycle **[Sonnet]**
- Admin BO CRUD (name, company, logo upload, greeting override, admin business-context text, pre-selected niche, language default) per §6.2.
- Referral token generation/hashing, signed `/r/{token}` URL, states (`created→sent→visited→in_progress→submitted→revoked/expired`), admin panel actions (generate/copy/regenerate/revoke/set expiry/mark sent).
- `ReferralGuard` middleware (token→session binding, first-visit lightweight company-name confirmation per §2.2.4).

### S1.4 — Taxonomy & service catalog: seed data + editor **[Sonnet]**
- Seed the full taxonomy (§4) and service catalog (§5, all 24 services with bilingual names/features) plus the per-niche recommended mappings excerpt as a starting dataset.
- Admin CRUD screens: taxonomy editor (add/edit/hide category+niche), service catalog editor (services, features, niche mappings, recommended flags, `saas_eligible`).
- **Pricing fields (per §0.1):** add an indicative `price_min`/`price_max` (or single price) column to `services`, editable in the catalog editor; same fields on `custom_service` records (admin-editable post-hoc, BO never sets price). Add a global `settings` toggle: "Show prices to BO" (off/on) — the BO-facing display work for this lands in S2.3/S2.6, this session only adds the data + admin controls.

### S1.5 — Lead pipeline skeleton **[Sonnet]**
- Kanban board (columns per §6.3 stage enum), drag-and-drop stage change with note + timestamped history, filters by category/niche/date. Styled per `design.md` §6.8 lead-card pattern.
- **Stage 1 exit criteria:** admin can log in, create a BO, generate a referral link, and see it appear on a pipeline board; taxonomy/catalog are editable.

---

## Stage 2 — Discovery Flow Shell & Non-AI Phases

### S2.1 — Phase-machine shell, autosave, i18n **[Sonnet]**
- Phase router/state machine (Greeting → Phase 0 → 1–6 → Review → Submit), phase rail component (`design.md` §6.1), sticky glass bottom action bar (§4.1).
- Debounced per-field autosave to `discovery_answers` (upsert), visible "Saved" pill, resume-from-last-phase on re-entry.
- i18n scaffolding: BG/EN JSON lang files, language toggle (persists). **Default language resolution per §0.1:** read the browser `Accept-Language` header on first visit (BG if reported, else EN) — no IP geolocation, no external call; BO-provisioned language default from admin (§6.2) takes precedence if set, header is only the fallback.
- **Depends on:** S1.1, S1.3.

### S2.2 — Greeting + Phase 1 Business Profile **[Sonnet]**
- Personalized greeting screen (name/company/logo/custom-greeting-override, BG/EN default copy from §3.0).
- Phase 1: profile confirm/edit, two-level taxonomy picker (searchable card grid), "Other/not listed" free-text niche flagged for admin. (DCP pre-highlight badge wiring is a stub here — actual DCP data arrives in S3.1; leave a clearly marked TODO hook.)

### S2.3 — Phase 2 Services Selection (no AI yet) **[Sonnet]**
- Gated catalog UI filtered by niche, service cards (icon, name, value line, expandable features, Add toggle, note field, priority star) — the shared "selected-service card" component from `design.md` §6.3, built generically enough to also host AI-suggested/custom entries later.
- "Add your own service" flow (name, description, feature chips, reference links).
- ✨ AI-suggestions button present but disabled/stubbed with a "coming soon" state — wired for real in S3.2.
- **Price display (per §0.1):** each service card conditionally shows its indicative price/range when the admin "Show prices to BO" setting (added in S1.4) is on; hidden entirely (no layout gap) when off. Custom/AI-suggested services show price only once an admin has set one post-hoc — otherwise no price element renders.

### S2.4 — Phase 3 Branding & Look/Feel **[Sonnet]**
- Style-direction chips (multi-select), color preferences (presets + free picker + "use my logo colors"), reference links with per-link notes.
- Asset upload pipeline: local disk storage, mime/extension whitelist, 15MB/file + 200MB/BO quota UI (design.md §6.7), Intervention Image thumbnailing.
- AI-suggestions button stubbed (wired in S3.2).

### S2.5 — Phase 4 & Phase 5 UI **[Sonnet]**
- Phase 4: content needs, social presence inputs.
- Phase 5: three toggleable modules (notifications, marketing/retention, lead-gen preference capture, admin/ops tooling) with sub-options, generic vendor-neutral labels only.
- All AI-suggestion buttons stubbed (wired in S3.3).

### S2.6 — Phase 6 + Review shell + Submit **[Sonnet]**
- Phase 6: billing model cards (one-time / build+support / SaaS-subscription — SaaS card conditionally shown per `saas_eligible` selections), budget slider (**EUR default**, BGN toggle per §0.1), timeline picker.
- **Approx. total price (per §0.1):** when the admin "Show prices to BO" setting is on, render a summary line/card summing indicative prices of all selected services (catalog, accepted-suggestion, and priced custom entries) — clearly labeled as an estimate, not a quote. Hidden entirely when the setting is off.
- Soft-vs-hard validation per §3.9: every phase freely skippable/back-navigable **except** Phase 1 niche and Phase 6 billing, which block Continue with an inline explanation (no other phase may block).
- Review screen shell: static/deterministic markdown preview (real AI compile arrives in S3.5), amend-instruction box UI (non-functional stub), submit confirmation modal, closing screen copy (BG/EN).
- **Stage 2 exit criteria:** a BO can walk the entire flow end-to-end with zero AI calls, freely skipping/appending notes everywhere except the two hard-gated phases, and reach a submit confirmation.

---

## Stage 3 — AI Layer

### S3.0 — AiClient plumbing **[Sonnet]**
- `AiClient` service wrapping the Anthropic Messages API (model/effort/temperature configurable), central request logging (`ai_calls` migration: bo_id, tool, model, input/output tokens, latency, cost estimate, status, vendor_leak flag).
- Tool-call scaffolding pattern (prompt template registry, input assembler interface, JSON-schema validator interface) — no actual prompts written yet, just the harness the Fable sessions below will fill in.
- **Depends on:** S1.1 (activity_events pattern), S2.1.

### S3.1 — Phase 0 Intake & DCP generation **[Fable · AI-flow]**
- Wire the free-prompt / guided-interview toggle UI (`design.md` §6.5) to real submission.
- Implement `dcp.generate` tool: prompt template (system policy + admin context + free text), JSON schema validation against the §3.1 DCP shape, `dcp_profiles` migration + versioned storage.
- Graceful failure path: empty DCP + retry offer, static-defaults fallback for downstream phases.
- Wire the Phase 1 "Suggested based on your description" niche pre-highlight badge (stub left in S2.2) to real DCP output.
- Agent-spawning: ≤2 parallel subagents max; prefer direct edits over spawning for this session's tightly-coupled prompt/schema/UI work.

### S3.2 — Suggestion tools: Services + Branding **[Fable · AI-flow]**
- `suggest.services` and `suggest.branding` tools implementing the §7.4 Suggestion Card contract (3–5 cards, ≥3 DCP-tied features, rationale, vendor-neutral).
- Wire the Suggestion Card component (`design.md` §6.2) into Phase 2 and Phase 3: accept→becomes selection (catalog link or custom service), dismiss (soft, undo-able), free-text note field on accepted cards, DCP-driven "Recommended for you — {reason}" ordering badges on the base catalog grid.
- ≤2 parallel subagents max.

### S3.3 — Suggestion tools: Content/Social + Growth **[Fable · AI-flow]**
- `suggest.content_social` and `suggest.growth` (per-module) tools, same contract, wired into Phase 4 and each Phase 5 module.
- ≤2 parallel subagents max.
- **Stage-3 AI-flow exit criteria:** the entire discovery interview — intake through every suggestion touchpoint — is live with real AI, every card/suggestion is skippable and every selection appendable with free text, matching the requirement in `design.md` §6.3/§6.4 exactly.

### S3.4 — Vendor-neutrality enforcement **[Fable · complex]**
- System-prompt policy text (shared across all tools), admin-managed blocklist (`settings`/dedicated table) + regex support, output filter with single-regeneration-on-hit and redact+log-`vendor_leak`-on-second-hit.
- Admin blocklist editor screen (basic CRUD; can be minimal UI, function over polish here).

### S3.5 — Spec compile, amend & fallback renderer **[Fable · complex]**
- `spec.compile` tool implementing the §7.5 markdown skeleton (10 sections), triggered on Review-screen open/regenerate.
- Wire the real markdown preview into the Review screen (replacing the S2.6 static stub).
- `spec.amend`: instruction box → revised markdown + change summary → versioned `spec_documents`/`spec_amendments`, section-level regeneration.
- Deterministic non-AI fallback renderer for budget-exhausted case (assembles spec straight from structured `discovery_answers`/`selected_services` — no AI prose).
- **Depends on:** S3.1–S3.3 (needs real DCP + selections to compile against).

### S3.6 — Token budgeting & safeguards **[Sonnet]**
- Global/per-BO caps, per-call-type caps, pre-flight budget check before every AI call, hard-stop vs. soft-warn config toggle.
- Rate limiting (max N calls/min per BO session, default 6).
- UI messaging: "AI suggestions are temporarily unavailable — continue manually" states across every AI touchpoint built in S3.1–S3.3/S3.5, falling back to `suggestion_presets` where available.

### S3.7 — Submit flow & discovery-flow QA **[Sonnet]**
- Full submit wiring: lead-stage transition to `discovery_complete`, closing screen, read-only post-submit view via the same referral link.
- End-to-end manual QA pass of the entire BO journey specifically verifying: every phase (except Phase 1/6) is skippable, every suggestion card can be dismissed without blocking, every selected/accepted item (catalog, AI-suggested, custom) supports free-text append, autosave/resume works, AI-unavailable fallback paths degrade gracefully.
- **Stage 3 exit criteria:** the BO discovery flow is feature-complete end-to-end with AI, matching the technical spec and the explicit skip/append requirement.

---

## Stage 4 — Admin Intelligence

### S4.1 — Dashboard real data **[Sonnet]**
- Wire the S1.2 dashboard shell to real KPI tiles (total BOs, links visited, in-progress, submitted, proposals sent, closed), AI usage summary (tokens/cost overall/monthly/top consumers), recent activity feed.

### S4.2 — BO detail page **[Sonnet]**
- Profile, activity timeline, per-phase discovery progress, structured answers view, uploaded asset gallery+downloads, DCP view, spec version list, AI usage & cost for this BO, per-BO token budget override control.
- **Depends on:** S3.1 (DCP), S3.5 (spec versions), S3.0 (ai_calls data).

### S4.3 — Kanban pipeline full wiring **[Sonnet]**
- Persist drag-and-drop stage changes with note + timestamp, full stage-history view, category/niche/date filters (builds on S1.5 skeleton with real BO/lead data now flowing from the discovery flow).

### S4.4 — Spec review & decision-surface UI **[Sonnet]**
- Rendered spec preview (reusing the BO-side renderer), raw markdown view, version diff list, scannable chip/card decision surface (services+features, billing, budget, timeline, branding directions) per §6.4.

### S4.5 — Proposal & assessment generators **[Fable · complex]**
- `proposal.generate` and `assessment.generate` tools (spec + admin notes → draft markdown), markdown editor for admin refinement, asset attachment, versioning, internal-only assessment mode (never shown to BO), upload-external-proposal alternative path.
- PDF export is **not** in this session — moved to Stage 5 (S5.5) per §0.1; leave the export button as a disabled/"coming soon" affordance here if convenient, but don't build the renderer yet.
- **Depends on:** S3.5 (spec markdown), S3.4 (must pass through the same vendor filter).

### S4.6 — Email content generators **[Fable · complex]**
- `email.generate` tool: Warm tease / Follow-up / Proposal cover, BG/EN, from BO context + spec, copy-to-clipboard editor UI. No sending (manual only, per non-goals).

### S4.7 — Content & funnel management screens **[Sonnet]**
- Suggestion-presets editor (per niche/phase), phase-copy overrides (titles/helper text/greeting templates per language), deepen the S1.4 taxonomy/service editors as needed, vendor blocklist editor polish (pairs with S3.4 backend).

### S4.8 — AI & system settings **[Sonnet]**
- Model/token/temperature/effort config screens, global+per-BO budget & alert-threshold settings, usage explorer (per period/BO/call-type with token counts and cost estimates against a configurable price table), prompt template viewer/editor with version history and "reset to default."
- **Stage 4 exit criteria:** operator can run the full lifecycle — provision a BO, watch them through the pipeline, review/amend their spec, generate a proposal and outreach emails, and monitor AI cost — entirely from the admin portal.

---

## Stage 5 — Polish & Hardening

### S5.1 — Mobile & motion polish **[Sonnet]**
- Full mobile pass on the BO flow against `design.md` §4.1/§5 (sticky bars, touch target sizing, entry-animation staggering, reduced-motion compliance); activity-timeline and spec-diff view visual polish in admin.

### S5.2 — Backups, security hardening, deployment **[Sonnet]**
- Nightly `pg_dump` + storage rsync to a second volume; rate-limit review; security headers; CSRF/token-hashing audit; Nginx + PHP-FPM + PostgreSQL + supervisor (queue worker) + cron (scheduler) deployment scripts for the target VPS; Let's Encrypt/certbot TLS setup.

### S5.3 — BG copy & seed content QA **[Sonnet]**
- Full review pass of all BG/EN i18n strings (tone, correctness, length-driven layout checks per `design.md` §7), seed taxonomy/catalog/preset content review.

### S5.4 — Automated tests & final QA script **[Sonnet]**
- Pest feature tests: referral auth/guard, answer upsert/autosave, budget enforcement, vendor filter behavior.
- Execute the manual BO-journey QA script end-to-end one final time, explicitly re-verifying the skip/append requirement across every phase and every card type before calling v1 done.

### S5.5 — Proposal PDF export **[Sonnet]**
- Server-side PDF render of the proposal markdown (per §0.1, deferred here from S4.5), wired to the "Export as PDF" affordance left stubbed in the proposal builder; styled to match the spec/proposal preview renderer.
- **Depends on:** S4.5.

### S5.6 — Advisory briefs for content & growth: generation, exemplars & deterministic gate **[Fable · AI-flow]**
- Add a short **advisory brief** to the Phase 4 (`suggest.content_social`) and Phase 5 (`suggest.growth`, per module) ✨ touchpoints — a plain-language "note from the studio" that gives the BO general direction and insight *alongside* the Suggestion Cards, to build trust and show we understood their situation. Content & growth stages only for now. This is a potential conversion lever (advertise "you'll get useful guidance about your business"), so quality is the whole game — the exemplar library + gate below exist to keep briefs specific and high-value, never generic.
- **One-shot generation, not chat:** the brief is generated in the *same* AI call as the cards — an optional `brief` field ({ `paragraph`, `bullets[]` }) added to the §7.4 contract — so there's no extra generation call and no conversational state. Content briefs cover **posts only** (no short-video scripts). (Grading is a separate async step, added in S5.7.)
- **Few-shot exemplar library — the primary quality lever.** Prompt adjectives barely move quality; curated DCP→brief exemplars do. New `brief_exemplars` table (context/niche tags, `dcp_excerpt`, `exemplar_brief`, `quality_notes`, `active`, `version`); seed 3–6 hand-written gold pairs that embody the house standard (per-niche flavor where possible). The assembler selects the most relevant exemplars and injects them as a §7.3-style context block. A read-only view is fine here; the full exemplar editor lands in S5.7.
- **Deterministic quality gate (synchronous, free) — runs before anything is shown.** Extend the validator: `brief` hard-capped (~600–800 chars, ≤4 bullets); a **generic-platitude blocklist** ("engage your audience", "post consistently", "leverage social media", …); and a **DCP-grounding check** — the brief must reference something concrete from their profile (niche / a pain-point / a goal token) or it's dropped. On any failure the brief is nulled (cards still return) and the drop reason is recorded. Honest limitation: "is this copy-paste content / a platitude" can't be perfectly detected in code — this gate is the cheap backstop that kills obvious junk before the S5.7 judge ever spends a call; the exemplars do the real quality work.
- **Gated to advice, never deliverable:** prompt gate — "1 short paragraph + up to 4 bullets of general direction/insight; NOT ready-to-publish copy, NOT captions/scripts, NOT a step-by-step action plan." (A "non-deliverable" rubric dimension re-checks this in S5.7.)
- **Prose → routes through the S3.4 vendor filter** like every other free-text output (highest vendor-leak surface).
- **Persistence — dedicated `advisory_briefs` table** (not `discovery_answers`, because S5.7 hangs scores/metadata off it): `business_owner_id`, phase + optional module, `brief` payload, `verdict` (in S5.6 set by the deterministic gate: `shown` | `dropped`), `drop_reason`, and **reproducibility metadata** — model/prompt version, exemplar-set version, and a DCP snapshot reference — so any brief can be traced back to exactly what produced it. Latest-per-(phase, module); regenerating supersedes. The Review screen / spec read the latest `shown` brief from here.
- **Frontend:** render the brief as a styled "note from the studio" callout above the card grid in `SuggestionPanel` — no accept/persist affordance on the brief itself (it's advisory, not a selection). In S5.6 the brief shows as soon as it passes the deterministic gate; S5.7 shifts it to graded async-reveal, so keep the render path decoupled from the initial cards response to make that change cheap.
- **Depends on:** S3.3 (the two tools + panel), S3.4 (vendor filter must be live), S3.5 (if the brief should surface in the compiled spec).

### S5.7 — Brief quality: rubric grader, eval harness & admin review **[Fable · complex]**
- The calibration + trust layer over S5.6. Adds an AI grader, the async-reveal wiring, the admin feedback loop, and an offline eval harness — the machinery that lets you actually *know* a brief is high-value and improve the engine over time.
- **`brief.grade` LLM-as-judge tool.** Rubric with explicit 1–5 dimensions — **Specificity** (references their real business), **Insight vs. platitude**, **Non-deliverable/safety** (stays advice; doesn't drift into copy or a step-by-step plan), **Credibility/tone** — weighted into a composite, returning per-dimension scores + short reasons. Runs on a cheap/small model with tight token caps; logged as its own `ai_calls` row and counted against S3.6 budgets. Caveat baked into the task: a same-family judge is lenient and correlated with the generator's blind spots — it's a filter, not a guarantee, which is exactly why the eval set + admin labeling below exist.
- **Async-reveal wiring (the chosen UX).** The cards render immediately from the S5.6 generate response; the brief is held, `brief.grade` fires as a second request, and the brief is revealed a beat later **only if** the composite clears the threshold. Cards are never blocked; a failed/slow grade simply means no brief appears.
- **Hard-gate is admin-configurable to protect against an uncalibrated judge:** a `mode` setting (`log_only` | `enforce`) + threshold. Ship in `log_only` first — score and persist every brief but reveal whatever passed the S5.6 deterministic gate — then flip to `enforce` once the threshold is calibrated against real data. Enforce is the intended steady state.
- **Persist everything, including hidden/low-value briefs** (per the requirement — you need to review them to judge whether the brief *or* the exemplars/rubric were at fault). Extend `advisory_briefs` with per-dimension scores, composite, judge model/version, rubric version, and `verdict` (`shown` | `hidden_low_value` | `dropped`). Combined with S5.6's input metadata, every low-value brief is fully reproducible.
- **Admin review & labeling surface** (extends the S4.x admin portal): list/filter briefs by verdict and score, view the DCP input + exemplars that were in context, and label each **"actually good / actually bad."** Those labels are the ground truth that calibrates both the exemplars and the rubric. Includes editors for the `brief_exemplars` library and the rubric config (dimensions, weights, threshold, mode).
- **Offline eval harness.** A small gold set of representative DCPs with human-graded target briefs, plus an artisan command that runs the current prompt + exemplars + rubric against it and reports pass-rate / regressions — so any change to the generation or grading engine is regression-tested before shipping, instead of judged by vibes. This is the real long-term "get it right" mechanism.
- **Depends on:** S5.6 (generation + `advisory_briefs` + exemplars), S3.4 (grader output/prose still vendor-safe), S3.6 (the extra grade call counts against token budgets/rate limits), S4.x admin portal (review surface hangs off it).

---

## Stage 6 — Public Landing Page

Not part of the closed discovery flow — this is the unauthenticated `/` route that markets the product to prospects who don't yet have a referral link, and funnels interest into the existing admin-driven referral process (§0.2). Can be scheduled any time after its dependencies are met; it doesn't block Stages 3–5, so pull it forward or leave it late depending on when you actually need outbound lead capture live.

### S6.1 — Lead-request data model & admin inbox **[Sonnet]**
- New `referral_requests` table: `name, company, email, phone, website_or_social (nullable), status (new|contacted|converted|dismissed), source_note, converted_bo_id (nullable), created_at`.
- Admin inbox screen (new nav item): list incoming requests, mark `contacted`/`dismissed`, and a **"Convert to BO"** action that pre-fills the existing S1.3 BO-creation form from the request's fields (name, company, optional website/social carried into the BO's admin-context field) and generates the referral link in the same flow — no duplicate data entry.
- **Depends on:** S1.3 (BO/referral admin flow being converted into).

### S6.2 — Landing page UI: hero, features, screenshots **[Sonnet]**
- Public marketing page at `/`, built on the same `design.md` token system but with landing-specific patterns: hero (value proposition + primary CTA), "how it works" section walking through the adaptive discovery concept, a feature-highlight grid (AI-guided phases, vendor-neutral custom solutions, structured spec output), and a **discovery-phase screenshot gallery/carousel**.
- Screenshots should be real captures of the built discovery flow — **depends on Stage 2** (phase UI) being in place; until then, use clearly-labeled placeholder frames matching the eventual layout so this session isn't blocked on exact pixel content.
- Mobile-first, fast-loading (static/SSR-friendly, minimal JS beyond the carousel and the CTA form trigger), matches the "tech-savvy, ultra-modern, blazing fast" brief — no heavy hero video/3D per `design.md` §8.
- **Depends on:** S0.1 (design tokens), Stage 2 (for real screenshots, soft dependency).

### S6.3 — Referral-request CTA form **[Sonnet]**
- CTA button opens a form (modal or dedicated `/request-access` route): **name, company, email, phone required**; **current website or social link optional**. Client + server-side validation.
- Spam/abuse mitigation appropriate to a public unauthenticated endpoint: honeypot field + basic per-IP rate limiting (consistent with the rate-limiting approach already used on BO endpoints per §9) — no heavier infra (no CAPTCHA service dependency, keeps with the "cheap and simple infra" principle).
- Success state: on-page confirmation copy (no email sent automatically — matches the spec's manual-email-only stance, §1.3/§6.5), writes to `referral_requests`.
- **Depends on:** S6.1 (data model), S6.2 (page it's embedded in).

---

## 3. Session count & model summary

| Stage | Sessions | Sonnet | Fable · AI-flow | Fable · complex |
|---|---|---|---|---|
| 0 | 1 | 1 | – | – |
| 1 | 5 | 5 | – | – |
| 2 | 6 | 6 | – | – |
| 3 | 8 | 3 | 3 | 2 |
| 4 | 8 | 6 | – | 2 |
| 5 | 7 | 5 | 1 | 1 |
| 6 | 3 | 3 | – | – |
| **Total** | **38** | **29** | **4** | **5** |

Fable 5 carries the onboarding interview + AI discovery suggestion sessions (S3.1–S3.3, plus the S5.6 advisory-brief add-on) plus five flagged high-complexity sessions (vendor-safety filtering S3.4, generative spec/proposal assembly S3.5/S4.5, email generation S4.6, brief-quality grading & eval S5.7). Everything else — scaffolding, CRUD, admin UI, infra, tests — runs on the current model at medium effort.
