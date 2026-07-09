<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailKind;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\AssessmentDocument;
use App\Models\BusinessOwner;
use App\Models\ProposalDocument;
use App\Models\Upload;
use App\Services\Ai\Tools\Assessment\AssessmentGenerator;
use App\Services\Ai\Tools\Email\EmailGenerator;
use App\Services\Ai\Tools\Proposal\ProposalGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §6.4/§6.5 Proposal builder — the admin-only surface for the three S4.5
 * generators. Everything here lives behind the auth+admin route group; the
 * assessment in particular must NEVER be serialized into any BO-facing
 * response (§6.4 — it is internal), which is why no discovery controller or
 * BO-side page touches these models.
 *
 * Ordering rule (§6.4): assessment first, proposal second — generateProposal
 * 422s without an assessment, mirroring the disabled button in the UI, and
 * ProposalGenerator itself throws as the last line of defense.
 */
class ProposalBuilderController extends Controller
{
    private const int MAX_UPLOAD_KB = 15 * 1024;

    public function show(BusinessOwner $businessOwner): Response
    {
        $businessOwner->load([
            'discoverySession.latestSpecDocument',
            'assessmentDocuments' => fn ($q) => $q->orderByDesc('version'),
            'proposalDocuments' => fn ($q) => $q->orderByDesc('version'),
            'emailDrafts' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'uploads' => fn ($q) => $q->orderByDesc('id'),
        ]);

        return Inertia::render('Admin/BusinessOwners/Proposal', [
            'businessOwner' => [
                'id' => $businessOwner->id,
                'name' => $businessOwner->name,
                'company' => $businessOwner->company,
                'language' => $businessOwner->language?->value ?? 'bg',
            ],
            'hasSpec' => $businessOwner->discoverySession?->latestSpecDocument !== null,
            'assessments' => $businessOwner->assessmentDocuments->map(fn (AssessmentDocument $doc) => [
                'id' => $doc->id,
                'version' => $doc->version,
                'markdown' => $doc->markdown,
                'generated_by' => $doc->generated_by,
                'created_at' => $doc->created_at?->toIso8601String(),
            ])->values(),
            'proposals' => $businessOwner->proposalDocuments->map(fn (ProposalDocument $doc) => [
                'id' => $doc->id,
                'version' => $doc->version,
                'markdown' => $doc->markdown,
                'generated_by' => $doc->generated_by,
                'attachments' => $doc->attachments ?? [],
                'upload' => $doc->upload_id ? $doc->upload?->toDiscoveryArray() : null,
                'created_at' => $doc->created_at?->toIso8601String(),
            ])->values(),
            'emailDrafts' => $businessOwner->emailDrafts->map(fn ($draft) => [
                'id' => $draft->id,
                'kind' => $draft->kind->value,
                'language' => $draft->language,
                'subject' => $draft->subject,
                'body' => $draft->body,
                'created_at' => $draft->created_at?->toIso8601String(),
            ])->values(),
            'uploads' => $businessOwner->uploads->map(fn (Upload $upload) => [
                'id' => $upload->id,
                'original_name' => $upload->original_name,
                'kind' => $upload->kind,
            ])->values(),
        ]);
    }

    public function generateAssessment(Request $request, BusinessOwner $businessOwner, AssessmentGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $document = $generator->generate($businessOwner, $data['notes'] ?? null);

        return $document
            ? back()->with('success', "Assessment v{$document->version} generated.")
            : back()->with('error', 'Assessment generation failed — try again, or write one manually and save it.');
    }

    /**
     * Save the admin's edited markdown as a new assessment version — the
     * edited version is what proposal pricing is grounded in (§6.4).
     */
    public function storeAssessment(Request $request, BusinessOwner $businessOwner): RedirectResponse
    {
        $data = $request->validate([
            'markdown' => ['required', 'string', 'max:200000'],
        ]);

        $version = ($businessOwner->assessmentDocuments()->max('version') ?? 0) + 1;

        AssessmentDocument::create([
            'business_owner_id' => $businessOwner->id,
            'version' => $version,
            'markdown' => $data['markdown'],
            'generated_by' => 'manual',
        ]);

        return back()->with('success', "Assessment v{$version} saved.");
    }

    public function generateProposal(BusinessOwner $businessOwner, ProposalGenerator $generator): RedirectResponse
    {
        abort_if(
            ! $businessOwner->assessmentDocuments()->exists(),
            422,
            'Generate (or write) an assessment first — the proposal grounds its pricing and timeline in it.',
        );

        $document = $generator->generate($businessOwner);

        return $document
            ? back()->with('success', "Proposal v{$document->version} generated.")
            : back()->with('error', 'Proposal generation failed — try again, or write one manually and save it.');
    }

    public function storeProposal(Request $request, BusinessOwner $businessOwner): RedirectResponse
    {
        $data = $request->validate([
            'markdown' => ['required', 'string', 'max:200000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'integer',
                Rule::exists('uploads', 'id')->where('business_owner_id', $businessOwner->id),
            ],
        ]);

        $version = ($businessOwner->proposalDocuments()->max('version') ?? 0) + 1;

        ProposalDocument::create([
            'business_owner_id' => $businessOwner->id,
            'version' => $version,
            'markdown' => $data['markdown'],
            'generated_by' => 'manual',
            'attachments' => $data['attachments'] ?? null,
        ]);

        return back()->with('success', "Proposal v{$version} saved.");
    }

    /**
     * §6.4 alternative path: an externally-written proposal file becomes a
     * proposal version of its own (generated_by=uploaded, markdown null),
     * stored on the same private local disk as discovery uploads.
     */
    public function uploadProposal(Request $request, BusinessOwner $businessOwner): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KB, 'mimes:pdf,docx,doc,odt,md,txt'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        $upload = Upload::create([
            'business_owner_id' => $businessOwner->id,
            'discovery_session_id' => $businessOwner->discoverySession?->id,
            'phase' => null,
            'path' => $file->store("proposals/{$businessOwner->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'kind' => 'proposal',
        ]);

        $version = ($businessOwner->proposalDocuments()->max('version') ?? 0) + 1;

        ProposalDocument::create([
            'business_owner_id' => $businessOwner->id,
            'version' => $version,
            'markdown' => null,
            'generated_by' => 'uploaded',
            'upload_id' => $upload->id,
        ]);

        return back()->with('success', "External proposal saved as v{$version}.");
    }

    public function generateEmail(Request $request, BusinessOwner $businessOwner, EmailGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(EmailKind::class)],
            'language' => ['required', Rule::enum(Language::class)],
        ]);

        $draft = $generator->generate(
            $businessOwner,
            EmailKind::from($data['kind']),
            Language::from($data['language']),
        );

        return $draft
            ? back()->with('success', 'Email draft generated.')
            : back()->with('error', 'Email generation failed — try again.');
    }
}
