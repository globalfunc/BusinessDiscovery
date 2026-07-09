<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BriefExemplar;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view of the S5.6 advisory-brief exemplar library (the gold pairs
 * injected into suggest.content_social / suggest.growth calls). Deliberately
 * no CRUD in S5.6 — exemplars are hand-written and seeded; the editor plus
 * the calibration loop that would justify changing them arrive with S5.7's
 * admin review & labeling surface.
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
}
