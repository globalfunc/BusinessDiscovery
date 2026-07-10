<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BriefExemplar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The S5.6 advisory-brief exemplar library (the gold pairs injected into
 * suggest.content_social / suggest.growth calls), with the S5.7 editor: the
 * calibration loop's second lever alongside the rubric. Editing an
 * exemplar's content bumps its version — advisory_briefs rows persist the
 * id+version set that was in context, so a rewrite must not silently rewrite
 * history. Rows are never hard-deleted for the same reason; retiring one is
 * `active => false`.
 */
class BriefExemplarController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/BriefExemplars/Index', [
            'exemplars' => BriefExemplar::query()
                ->orderByDesc('active')
                ->orderBy('id')
                ->get()
                ->map(fn (BriefExemplar $exemplar) => [
                    'id' => $exemplar->id,
                    'context_tags' => $exemplar->context_tags,
                    'dcp_excerpt' => $exemplar->dcp_excerpt,
                    'exemplar_brief' => $exemplar->exemplar_brief,
                    'quality_notes' => $exemplar->quality_notes,
                    'active' => $exemplar->active,
                    'version' => $exemplar->version,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BriefExemplar::create([...$this->validated($request), 'version' => 1]);

        return back()->with('success', 'Exemplar added.');
    }

    public function update(Request $request, BriefExemplar $briefExemplar): RedirectResponse
    {
        $data = $this->validated($request);

        $contentChanged = $data['dcp_excerpt'] !== $briefExemplar->dcp_excerpt
            || $data['exemplar_brief'] != $briefExemplar->exemplar_brief
            || array_values($data['context_tags']) != array_values((array) $briefExemplar->context_tags);

        $briefExemplar->update([
            ...$data,
            'version' => $briefExemplar->version + ($contentChanged ? 1 : 0),
        ]);

        return back()->with('success', 'Exemplar updated.');
    }

    /**
     * @return array{context_tags: string[], dcp_excerpt: string, exemplar_brief: array{paragraph: string, bullets: string[]}, quality_notes: ?string, active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'context_tags' => ['array'],
            'context_tags.*' => ['string', 'max:64'],
            'dcp_excerpt' => ['required', 'string', 'max:2000'],
            'paragraph' => ['required', 'string', 'max:800'],
            'bullets' => ['array', 'max:4'],
            'bullets.*' => ['string', 'max:300'],
            'quality_notes' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ]);

        return [
            'context_tags' => array_values(array_filter($data['context_tags'] ?? [], fn ($tag) => trim($tag) !== '')),
            'dcp_excerpt' => $data['dcp_excerpt'],
            'exemplar_brief' => [
                'paragraph' => $data['paragraph'],
                'bullets' => array_values(array_filter($data['bullets'] ?? [], fn ($bullet) => trim($bullet) !== '')),
            ],
            'quality_notes' => $data['quality_notes'] ?? null,
            'active' => $data['active'] ?? true,
        ];
    }
}
