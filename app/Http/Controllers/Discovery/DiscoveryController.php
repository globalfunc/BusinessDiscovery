<?php

namespace App\Http\Controllers\Discovery;

use App\Enums\DiscoveryPhase;
use App\Enums\Language;
use App\Enums\PipelineStage;
use App\Enums\ReferralTokenState;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\SelectedService;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Models\Upload;
use App\Support\DiscoverySpecRenderer;
use App\Support\LanguageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    public function show(Request $request, ?string $phase = null): Response|RedirectResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $language = $this->resolveAndPersistLanguage($request, $businessOwner);

        $session = DiscoverySession::firstOrCreate(
            ['business_owner_id' => $businessOwner->id],
            ['started_at' => now(), 'language' => $language, 'current_phase' => DiscoveryPhase::Phase0],
        );

        if ($session->language !== $language) {
            $session->update(['language' => $language]);
        }

        $ordered = DiscoveryPhase::ordered();
        $currentIndex = array_search($session->current_phase, $ordered, true);

        if ($phase === null) {
            return redirect()->route('discovery.show', ['phase' => $session->current_phase->value]);
        }

        $targetPhase = DiscoveryPhase::tryFrom($phase);
        if ($targetPhase === null) {
            return redirect()->route('discovery.show', ['phase' => $session->current_phase->value]);
        }

        $targetIndex = array_search($targetPhase, $ordered, true);
        if ($targetIndex > $currentIndex) {
            // Not reached yet — bounce back to the furthest reached phase.
            return redirect()->route('discovery.show', ['phase' => $session->current_phase->value]);
        }

        $answers = $session->answers()
            ->where('phase', $targetPhase->value)
            ->get()
            ->mapWithKeys(fn ($answer) => [$answer->field_key => $answer->value]);

        $taxonomyCategories = $targetPhase === DiscoveryPhase::Phase1
            ? TaxonomyCategory::query()
                ->where('hidden', false)
                ->orderBy('sort')
                ->with(['niches' => fn ($query) => $query->where('hidden', false)->orderBy('sort')])
                ->get()
                ->map(fn (TaxonomyCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'niches' => $category->niches->map(fn (TaxonomyNiche $niche) => [
                        'id' => $niche->id,
                        'name' => $niche->name,
                    ]),
                ])
            : null;

        // Phase 1 needs the latest DCP for the "Suggested based on your
        // description" pre-highlight (§3.2) and the retry-on-failure banner.
        $dcp = null;
        if ($targetPhase === DiscoveryPhase::Phase1) {
            $profile = $session->latestDcpProfile;

            if ($profile !== null) {
                $detected = null;
                $nicheId = $profile->payload['detected_niche']['niche_id'] ?? null;

                if (! $profile->isEmpty() && is_int($nicheId)) {
                    $detected = [
                        'niche_id' => $nicheId,
                        'category_id' => TaxonomyNiche::whereKey($nicheId)->value('taxonomy_category_id'),
                        'confidence' => $profile->payload['detected_niche']['confidence'] ?? null,
                    ];
                }

                $dcp = [
                    'status' => $profile->isEmpty() ? 'empty' : 'ok',
                    'detected_niche' => $detected,
                ];
            }
        }

        $serviceCatalog = null;
        $selectedServices = null;
        $showPricesToBo = false;

        if ($targetPhase === DiscoveryPhase::Phase2) {
            $showPricesToBo = (bool) (Setting::query()->where('key', 'show_prices_to_bo')->first()?->value['enabled'] ?? false);

            $nicheId = $session->answers()
                ->where('phase', DiscoveryPhase::Phase1->value)
                ->where('field_key', 'niche_id')
                ->value('value');
            $nicheId = is_int($nicheId) ? $nicheId : null;

            $serviceCatalog = $this->gatedServiceCatalog($nicheId, $this->dcpSignalsForOrdering($session));
            $selectedServices = $session->selectedServices()->orderBy('created_at')->get()
                ->map(fn (SelectedService $s) => $s->toDiscoveryArray())
                ->values();
        }

        $uploads = null;
        $uploadQuota = null;
        if ($targetPhase === DiscoveryPhase::Phase3) {
            $uploads = Upload::where('business_owner_id', $businessOwner->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn (Upload $upload) => $upload->toDiscoveryArray())
                ->values();
            $uploadQuota = [
                'used' => (int) Upload::where('business_owner_id', $businessOwner->id)->sum('size'),
                'limit' => 200 * 1024 * 1024,
            ];
        }

        $saasEligible = false;
        $approxTotal = null;
        if ($targetPhase === DiscoveryPhase::Phase6) {
            $showPricesToBo = (bool) (Setting::query()->where('key', 'show_prices_to_bo')->first()?->value['enabled'] ?? false);
            $saasEligible = $session->selectedServices()
                ->whereNotNull('service_id')
                ->whereHas('service', fn ($query) => $query->where('saas_eligible', true))
                ->exists();
            $approxTotal = $showPricesToBo ? $this->computeApproxTotal($session) : null;
        }

        $reviewMarkdown = null;
        if ($targetPhase === DiscoveryPhase::Review) {
            $reviewMarkdown = DiscoverySpecRenderer::render($session, $businessOwner);
        }

        return Inertia::render('Discovery/Show', [
            'businessOwner' => [
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'pre_selected_niche_id' => $businessOwner->pre_selected_niche_id,
                'pre_selected_category_id' => $businessOwner->preSelectedNiche?->taxonomy_category_id,
                'has_logo' => $businessOwner->logo_path !== null,
            ],
            'taxonomyCategories' => $taxonomyCategories,
            'session' => [
                'status' => $session->status,
                'current_phase' => $session->current_phase->value,
            ],
            'phase' => $targetPhase->value,
            'phases' => collect($ordered)->map(fn (DiscoveryPhase $p) => [
                'key' => $p->value,
                'label' => $p->label(),
            ]),
            'visitedPhaseKeys' => collect(array_slice($ordered, 0, $currentIndex + 1))
                ->map(fn (DiscoveryPhase $p) => $p->value),
            'answers' => $answers,
            'dcp' => $dcp,
            'language' => $language->value,
            'serviceCatalog' => $serviceCatalog,
            'selectedServices' => $selectedServices,
            'showPricesToBo' => $showPricesToBo,
            'uploads' => $uploads,
            'uploadQuota' => $uploadQuota,
            'saasEligible' => $saasEligible,
            'approxTotal' => $approxTotal,
            'reviewMarkdown' => $reviewMarkdown,
        ]);
    }

    /**
     * Sums indicative prices across all selected services for the Phase 6
     * "Approx. total" card (design.md §6.2b): catalog-linked entries price
     * from the linked Service record (selected_services never copies price
     * onto itself at add-time — see SelectedServiceController::store()),
     * custom entries price from their own admin-set price_min/price_max.
     * Entries with no price set on either side are skipped entirely.
     *
     * @return array{min: int, max: int}|null
     */
    private function computeApproxTotal(DiscoverySession $session): ?array
    {
        $selected = $session->selectedServices()->with('service')->get();

        $min = 0;
        $max = 0;
        $hasAny = false;

        foreach ($selected as $selectedService) {
            $priceMin = $selectedService->service_id !== null
                ? $selectedService->service?->price_min
                : $selectedService->price_min;
            $priceMax = $selectedService->service_id !== null
                ? $selectedService->service?->price_max
                : $selectedService->price_max;

            if ($priceMin === null && $priceMax === null) {
                continue;
            }

            $hasAny = true;
            $min += $priceMin ?? $priceMax;
            $max += $priceMax ?? $priceMin;
        }

        return $hasAny ? ['min' => $min, 'max' => $max] : null;
    }

    /**
     * Full catalog when no niche is confirmed yet (e.g. "Other" free-text
     * niche); niche-filtered + recommended-flagged otherwise.
     *
     * Ordering (§3.3): DCP "Recommended for you" matches first, then
     * recommended-for-niche, then alphabetical by English name. A service
     * earns the personalized badge when its tags intersect the DCP's
     * priority signals / pain points; `dcp_reason` (nullable) carries the
     * matching pain-point label or signal for the "Recommended for you —
     * {reason}" badge (design.md §6.2 / tech-spec §3.3).
     *
     * @param  array{tokens: string[], reasons: array<string, string>}|null  $dcpSignals
     */
    private function gatedServiceCatalog(?int $nicheId, ?array $dcpSignals): Collection
    {
        $services = Service::query()
            ->where('hidden', false)
            ->when($nicheId !== null, fn ($query) => $query->with(['niches' => fn ($q) => $q->where('taxonomy_niches.id', $nicheId)]))
            ->get();

        return $services
            ->map(function (Service $service) use ($nicheId, $dcpSignals) {
                $recommended = false;
                if ($nicheId !== null) {
                    $pivotNiche = $service->niches->firstWhere('id', $nicheId);
                    $recommended = (bool) ($pivotNiche?->pivot->recommended ?? false);
                }

                [$dcpRecommended, $dcpReason] = $this->dcpMatch($service, $dcpSignals);

                return [
                    'id' => $service->id,
                    'key' => $service->key,
                    'name' => $service->name,
                    'one_liner' => $service->one_liner,
                    'base_features' => $service->base_features,
                    'saas_eligible' => $service->saas_eligible,
                    'price_min' => $service->price_min,
                    'price_max' => $service->price_max,
                    'recommended' => $recommended,
                    'dcp_recommended' => $dcpRecommended,
                    'dcp_reason' => $dcpReason,
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($b['dcp_recommended'] <=> $a['dcp_recommended']),
                fn ($a, $b) => ($b['recommended'] <=> $a['recommended']),
                fn ($a, $b) => ($a['name']['en'] <=> $b['name']['en']),
            ])
            ->values();
    }

    /**
     * A service matches the DCP when its tags overlap the DCP's priority
     * signals / pain-point ids. Reason prefers the matching pain-point label
     * (the emotional hook, in the BO's language); otherwise a humanized signal.
     *
     * @param  array{tokens: string[], reasons: array<string, string>}|null  $dcpSignals
     * @return array{0: bool, 1: string|null}
     */
    private function dcpMatch(Service $service, ?array $dcpSignals): array
    {
        if ($dcpSignals === null) {
            return [false, null];
        }

        $tags = array_filter((array) $service->tags, 'is_string');
        $matched = array_values(array_intersect($tags, $dcpSignals['tokens']));

        if ($matched === []) {
            return [false, null];
        }

        return [true, $dcpSignals['reasons'][$matched[0]] ?? null];
    }

    /**
     * Builds the token set + human reasons from the latest usable DCP for
     * catalog ordering. Null when there's no usable DCP — the grid then
     * falls back to niche-recommended ordering only (§3.9 static fallback).
     *
     * @return array{tokens: string[], reasons: array<string, string>}|null
     */
    private function dcpSignalsForOrdering(DiscoverySession $session): ?array
    {
        $profile = $session->latestDcpProfile;

        if ($profile === null || $profile->isEmpty()) {
            return null;
        }

        $reasons = [];

        foreach ((array) ($profile->payload['pain_points'] ?? []) as $pain) {
            if (is_array($pain) && is_string($pain['id'] ?? null) && is_string($pain['label'] ?? null)) {
                $reasons[$pain['id']] = $pain['label'];
            }
        }

        foreach (array_filter((array) ($profile->payload['priority_signals'] ?? []), 'is_string') as $signal) {
            $reasons[$signal] ??= str_replace('_', ' ', $signal);
        }

        $tokens = array_keys($reasons);

        return $tokens === [] ? null : ['tokens' => $tokens, 'reasons' => $reasons];
    }

    public function updateAnswer(Request $request): JsonResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $data = $request->validate([
            'phase' => ['required', 'string', 'in:'.implode(',', array_column(DiscoveryPhase::cases(), 'value'))],
            'field_key' => ['required', 'string', 'max:255'],
            'value' => ['nullable'],
        ]);

        $session = DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();

        $answer = $session->answers()->updateOrCreate(
            ['phase' => $data['phase'], 'field_key' => $data['field_key']],
            ['value' => $data['value'] ?? null],
        );

        if ($data['phase'] === DiscoveryPhase::Phase1->value && $data['field_key'] === 'custom_niche_text') {
            $this->flagCustomNicheForAdminReview($businessOwner, $session, $data['value'] ?? null);
        }

        return response()->json([
            'saved' => true,
            'updated_at' => $answer->updated_at?->toIso8601String(),
        ]);
    }

    public function navigate(Request $request): RedirectResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');

        $data = $request->validate([
            'to' => ['required', 'string', 'in:'.implode(',', array_column(DiscoveryPhase::cases(), 'value'))],
        ]);

        $session = DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();

        $ordered = DiscoveryPhase::ordered();
        $targetPhase = DiscoveryPhase::from($data['to']);
        $currentIndex = array_search($session->current_phase, $ordered, true);
        $targetIndex = array_search($targetPhase, $ordered, true);

        abort_if($targetIndex > $currentIndex + 1, 403, 'That phase has not been reached yet.');

        if ($session->current_phase === DiscoveryPhase::Phase6 && $targetIndex > $currentIndex) {
            $hasBillingModel = $session->answers()
                ->where('phase', DiscoveryPhase::Phase6->value)
                ->where('field_key', 'billing_model')
                ->whereNotNull('value')
                ->exists();

            abort_unless($hasBillingModel, 422, 'Choose a billing model before continuing.');
        }

        if ($targetIndex > $currentIndex) {
            $session->update(['current_phase' => $targetPhase]);
        }

        return redirect()->route('discovery.show', ['phase' => $targetPhase->value]);
    }

    public function submit(Request $request): RedirectResponse
    {
        /** @var BusinessOwner $businessOwner */
        $businessOwner = $request->attributes->get('businessOwner');
        /** @var ReferralToken $referralToken */
        $referralToken = $request->attributes->get('referralToken');

        $session = DiscoverySession::where('business_owner_id', $businessOwner->id)->firstOrFail();

        $session->update(['status' => 'submitted', 'submitted_at' => now()]);
        $referralToken->update(['state' => ReferralTokenState::Submitted]);

        if ($businessOwner->current_stage !== PipelineStage::DiscoveryComplete) {
            $businessOwner->update(['current_stage' => PipelineStage::DiscoveryComplete]);
        }

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'discovery_submitted',
            'payload' => ['discovery_session_id' => $session->id],
        ]);

        return redirect()->route('discovery.show', ['phase' => DiscoveryPhase::Review->value]);
    }

    public function setLanguage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lang' => ['required', 'string', 'in:bg,en'],
        ]);

        Cookie::queue(LanguageResolver::COOKIE_NAME, $data['lang'], 525600);

        $businessOwnerId = $request->session()->get('business_owner_id');
        if ($businessOwnerId !== null) {
            DiscoverySession::where('business_owner_id', $businessOwnerId)
                ->update(['language' => $data['lang']]);
        }

        return redirect()->back();
    }

    /**
     * "Other / not listed" niches aren't in the catalog, so flag them for the
     * admin to review and possibly turn into a real taxonomy niche later.
     * Fires once per session — later edits to the text don't re-flag.
     */
    private function flagCustomNicheForAdminReview(BusinessOwner $businessOwner, DiscoverySession $session, mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $alreadyFlagged = ActivityEvent::where('business_owner_id', $businessOwner->id)
            ->where('type', 'custom_niche_flagged')
            ->exists();

        if ($alreadyFlagged) {
            return;
        }

        ActivityEvent::create([
            'business_owner_id' => $businessOwner->id,
            'type' => 'custom_niche_flagged',
            'payload' => ['discovery_session_id' => $session->id, 'custom_niche_text' => $value],
        ]);
    }

    private function resolveAndPersistLanguage(Request $request, BusinessOwner $businessOwner): Language
    {
        $language = LanguageResolver::resolve($request, $businessOwner);

        if ($request->cookie(LanguageResolver::COOKIE_NAME) === null) {
            Cookie::queue(LanguageResolver::COOKIE_NAME, $language->value, 525600);
        }

        return $language;
    }
}
