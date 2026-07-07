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
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Support\LanguageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Discovery/Show', [
            'businessOwner' => [
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'pre_selected_niche_id' => $businessOwner->pre_selected_niche_id,
                'pre_selected_category_id' => $businessOwner->preSelectedNiche?->taxonomy_category_id,
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
            'language' => $language->value,
        ]);
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
