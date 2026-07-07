<?php

namespace App\Support;

use App\Enums\Language;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\SelectedService;
use App\Models\TaxonomyNiche;
use App\Models\Upload;
use Illuminate\Support\Collection;

/**
 * Assembles the §7.5 markdown spec skeleton (10 sections) straight from
 * structured discovery_answers/selected_services/uploads — no AI prose.
 *
 * This is the S2.6 static Review preview. S3.5's `spec.compile` replaces the
 * body of each section with AI-authored prose, but its "budget exhausted"
 * fallback renderer should produce the same 10-section shape as this class
 * (see the S2.6 handoff note) — reuse this renderer there rather than
 * re-deriving the section layout.
 */
class DiscoverySpecRenderer
{
    /** @var array<string, mixed> */
    private array $lang;

    private function __construct(private readonly Language $language)
    {
        $path = resource_path('js/lang/'.$language->value.'.json');
        $this->lang = json_decode(file_get_contents($path), true) ?? [];
    }

    public static function render(DiscoverySession $session, BusinessOwner $businessOwner): string
    {
        return (new self($session->language))->build($session, $businessOwner);
    }

    private function build(DiscoverySession $session, BusinessOwner $businessOwner): string
    {
        $answers = $session->answers()->get()->groupBy('phase')
            ->map(fn ($group) => $group->mapWithKeys(fn ($a) => [$a->field_key => $a->value]));

        $phase1 = $answers->get('phase_1', collect());
        $phase3 = $answers->get('phase_3', collect());
        $phase4 = $answers->get('phase_4', collect());
        $phase5 = $answers->get('phase_5', collect());
        $phase6 = $answers->get('phase_6', collect());

        $selectedServices = $session->selectedServices()->with('service')->get();
        $uploads = $session->uploads()->get();

        $sections = [
            $this->businessOverview($businessOwner, $phase1, $answers->get('phase_0', collect())),
            $this->goalsPainsStrengths($answers->get('phase_0', collect())),
            $this->catalogServices($selectedServices),
            $this->customServices($selectedServices),
            $this->branding($phase3, $businessOwner),
            $this->contentSocial($phase4),
            $this->growthOperations($phase5),
            $this->billingBudgetTimeline($phase6),
            $this->uploadedAssets($uploads),
            $this->openQuestions(),
        ];

        return implode("\n\n", $sections);
    }

    private function t(string $key, array $vars = []): string
    {
        $value = data_get($this->lang, $key);
        if (! is_string($value)) {
            return $key;
        }
        foreach ($vars as $k => $v) {
            $value = str_replace('{'.$k.'}', (string) $v, $value);
        }

        return $value;
    }

    /** @param array<int, string> $lines */
    private function section(string $titleKey, array $lines): string
    {
        $body = count($lines) > 0 ? implode("\n", $lines) : $this->t('review.noneRecorded');

        return "## {$this->t($titleKey)}\n\n{$body}";
    }

    private function businessOverview(BusinessOwner $businessOwner, $phase1, $phase0): string
    {
        $name = $phase1->get('profile_name') ?? $businessOwner->name;
        $company = $phase1->get('profile_company') ?? $businessOwner->company;
        $website = $phase1->get('profile_website');
        $nicheId = $phase1->get('niche_id');
        $customNiche = $phase1->get('custom_niche_text');

        $lines = [
            '- '.$this->t('review.fields.name').': '.$name,
            '- '.$this->t('review.fields.company').': '.$company,
        ];

        if (is_string($website) && trim($website) !== '') {
            $lines[] = '- '.$this->t('review.fields.website').': '.$website;
        }

        if (is_int($nicheId)) {
            $niche = TaxonomyNiche::with('category')->find($nicheId);
            if ($niche !== null) {
                $lang = $this->language->value;
                $lines[] = '- '.$this->t('review.fields.niche').': '.($niche->category->name[$lang] ?? '').' — '.($niche->name[$lang] ?? '');
            }
        } elseif (is_string($customNiche) && trim($customNiche) !== '') {
            $lines[] = '- '.$this->t('review.fields.niche').': '.$customNiche.' ('.$this->t('review.customNicheFlag').')';
        }

        $intake = $phase0->get('notes');
        if (is_string($intake) && trim($intake) !== '') {
            $lines[] = '- '.$this->t('review.fields.intakeNotes').': '.$intake;
        }

        return $this->section('review.sections.overview', $lines);
    }

    private function goalsPainsStrengths($phase0): string
    {
        $lines = [$this->t('review.dcpPending')];

        $intake = $phase0->get('notes');
        if (is_string($intake) && trim($intake) !== '') {
            $lines[] = '- '.$this->t('review.fields.intakeNotes').': '.$intake;
        }

        return $this->section('review.sections.goals', $lines);
    }

    /** @param Collection<int, SelectedService> $selectedServices */
    private function catalogServices($selectedServices): string
    {
        $lang = $this->language->value;
        $lines = [];

        foreach ($selectedServices->where('custom', false) as $selected) {
            $name = $selected->service?->name[$lang] ?? $selected->name ?? '';
            $lines[] = $this->serviceBlock($name, $selected);
        }

        return $this->section('review.sections.selectedServices', $lines);
    }

    /** @param Collection<int, SelectedService> $selectedServices */
    private function customServices($selectedServices): string
    {
        $lines = [];

        foreach ($selectedServices->where('custom', true) as $selected) {
            $lines[] = $this->serviceBlock($selected->name ?? '', $selected);
            if (! empty($selected->reference_links)) {
                foreach ($selected->reference_links as $link) {
                    $lines[] = '  - '.$this->t('review.fields.referenceLink').': '.$link;
                }
            }
        }

        return $this->section('review.sections.customServices', $lines);
    }

    private function serviceBlock(string $name, SelectedService $selected): string
    {
        $marker = $selected->priority ? ' ⭐' : '';
        $line = '- **'.$name.'**'.$marker;
        $features = $selected->features ?? [];
        if (count($features) > 0) {
            $line .= "\n  - ".$this->t('review.fields.features').': '.implode('; ', $features);
        }
        if (! empty($selected->note)) {
            $line .= "\n  - ".$this->t('review.fields.note').': '.$selected->note;
        }

        return $line;
    }

    private function branding($phase3, BusinessOwner $businessOwner): string
    {
        $lines = [];

        $styleChips = $phase3->get('style_chips');
        if (is_array($styleChips) && count($styleChips) > 0) {
            $labels = array_map(fn ($key) => $this->t("phase3.styleChips.{$key}"), $styleChips);
            $lines[] = '- '.$this->t('review.fields.styleDirection').': '.implode(', ', $labels);
        }

        $colorPreset = $phase3->get('color_preset');
        $colorCustomHex = $phase3->get('color_custom_hex');
        if (is_string($colorPreset) && $colorPreset !== '') {
            $lines[] = '- '.$this->t('review.fields.colorPreference').': '.$colorPreset;
        } elseif (is_string($colorCustomHex) && $colorCustomHex !== '') {
            $lines[] = '- '.$this->t('review.fields.colorPreference').': '.$colorCustomHex;
        }

        $referenceLinks = $phase3->get('reference_links');
        if (is_array($referenceLinks)) {
            foreach ($referenceLinks as $entry) {
                $url = $entry['url'] ?? null;
                if (! is_string($url) || $url === '') {
                    continue;
                }
                $note = $entry['note'] ?? '';
                $lines[] = '- '.$this->t('review.fields.referenceLink').': '.$url.($note !== '' ? ' — '.$note : '');
            }
        }

        return $this->section('review.sections.branding', $lines);
    }

    private function contentSocial($phase4): string
    {
        $lines = [];

        $contentNeeds = $phase4->get('content_needs');
        if (is_array($contentNeeds) && count($contentNeeds) > 0) {
            $labels = array_map(fn ($key) => $this->t("phase4.contentNeeds.{$key}"), $contentNeeds);
            $lines[] = '- '.$this->t('review.fields.contentNeeds').': '.implode(', ', $labels);
        }

        $platforms = array_merge(
            array_map(fn ($key) => $this->t("phase4.platforms.{$key}"), $phase4->get('social_platforms') ?? []),
            $phase4->get('other_platforms') ?? [],
        );
        if (count($platforms) > 0) {
            $lines[] = '- '.$this->t('review.fields.socialPlatforms').': '.implode(', ', $platforms);
        }

        $cadence = $phase4->get('posting_cadence');
        if (is_string($cadence) && $cadence !== '') {
            $lines[] = '- '.$this->t('review.fields.postingCadence').': '.$this->t("phase4.cadence.{$cadence}");
        }

        $interest = $phase4->get('content_assist_interest');
        if (is_string($interest) && $interest !== '') {
            $lines[] = '- '.$this->t('review.fields.contentAssistInterest').': '.$this->t("phase4.interest.{$interest}");
        }

        return $this->section('review.sections.contentSocial', $lines);
    }

    private function growthOperations($phase5): string
    {
        $lines = [];
        $enabledModules = $phase5->get('enabled_modules') ?? [];

        $optionFieldByModule = [
            'notifications' => 'notifications_options',
            'marketing' => 'marketing_options',
            'leadgen' => 'leadgen_options',
            'admin_ops' => 'admin_ops_options',
        ];

        foreach ($enabledModules as $moduleKey) {
            $optionsField = $optionFieldByModule[$moduleKey] ?? null;
            $options = $optionsField !== null ? ($phase5->get($optionsField) ?? []) : [];
            $labels = array_map(fn ($key) => $this->t("phase5.modules.{$moduleKey}.options.{$key}"), $options);

            $line = '- **'.$this->t("phase5.modules.{$moduleKey}.title").'**';
            if (count($labels) > 0) {
                $line .= ': '.implode(', ', $labels);
            }
            $lines[] = $line;

            if ($moduleKey === 'leadgen') {
                $interest = $phase5->get('leadgen_managed_interest');
                if (is_string($interest) && $interest !== '') {
                    $lines[] = '  - '.$this->t('review.fields.leadgenManagedInterest').': '.$this->t("phase4.interest.{$interest}");
                }
            }
        }

        return $this->section('review.sections.growthOperations', $lines);
    }

    private function billingBudgetTimeline($phase6): string
    {
        $lines = [];

        $billingModel = $phase6->get('billing_model');
        if (is_string($billingModel) && $billingModel !== '') {
            $lines[] = '- '.$this->t('review.fields.billingModel').': '.$this->t("phase6.billing.{$billingModel}.title");
        }

        $budgetMin = $phase6->get('budget_min');
        $budgetMax = $phase6->get('budget_max');
        if (is_numeric($budgetMin) && is_numeric($budgetMax)) {
            $lines[] = '- '.$this->t('review.fields.budget').': €'.number_format((float) $budgetMin).' – €'.number_format((float) $budgetMax);
        }

        $timelineChoice = $phase6->get('timeline_choice');
        if (is_string($timelineChoice) && $timelineChoice !== '') {
            $lines[] = '- '.$this->t('review.fields.timeline').': '.$this->t("phase6.timeline.{$timelineChoice}");
        }

        $timelineNote = $phase6->get('timeline_note');
        if (is_string($timelineNote) && trim($timelineNote) !== '') {
            $lines[] = '- '.$this->t('review.fields.timelineNote').': '.$timelineNote;
        }

        return $this->section('review.sections.billingBudgetTimeline', $lines);
    }

    /** @param Collection<int, Upload> $uploads */
    private function uploadedAssets($uploads): string
    {
        $lines = $uploads->map(fn (Upload $upload) => '- '.$upload->original_name.' ('.$upload->mime.')')->all();

        return $this->section('review.sections.assets', $lines);
    }

    private function openQuestions(): string
    {
        $lines = [
            '- '.$this->t('review.openQuestions.scope'),
            '- '.$this->t('review.openQuestions.timeline'),
            '- '.$this->t('review.openQuestions.integrations'),
        ];

        return $this->section('review.sections.openQuestions', $lines);
    }
}
