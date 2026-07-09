<?php

namespace App\Services\Ai\Tools\Email;

use App\Enums\EmailKind;
use App\Enums\Language;
use App\Models\BusinessOwner;
use App\Services\Ai\ContextBlock;
use App\Services\Ai\ContextBlockType;
use App\Services\Ai\PromptTemplateRegistry;
use App\Services\Ai\Support\DcpDigest;
use App\Services\Ai\Support\SpecSource;

/**
 * §7.3 block assembly for email.generate (§6.5): BO context + spec, per the
 * spec — independent of assessment/proposal, so it works at any pipeline
 * stage (a warm tease usually fires before discovery even starts, when the
 * spec/DCP blocks are simply empty and get dropped).
 */
class EmailAssembler
{
    public function __construct(protected readonly PromptTemplateRegistry $templates) {}

    /**
     * @return ContextBlock[]
     */
    public function assemble(BusinessOwner $businessOwner, EmailKind $kind, Language $language): array
    {
        return [
            new ContextBlock(ContextBlockType::SystemPolicy, $this->templates->get('email.generate')->systemPrompt()),
            new ContextBlock(ContextBlockType::AdminContext, $this->adminContext($businessOwner)),
            new ContextBlock(ContextBlockType::Dcp, $businessOwner->discoverySession ? DcpDigest::for($businessOwner->discoverySession) : ''),
            new ContextBlock(ContextBlockType::SpecDocument, SpecSource::markdownFor($businessOwner)),
            new ContextBlock(ContextBlockType::TaskInstruction, $this->taskInstruction($kind, $language)),
        ];
    }

    private function adminContext(BusinessOwner $businessOwner): string
    {
        return implode("\n", array_filter([
            "Recipient: {$businessOwner->name}".($businessOwner->company ? " ({$businessOwner->company})" : ''),
            trim((string) $businessOwner->admin_context),
        ]));
    }

    private function taskInstruction(EmailKind $kind, Language $language): string
    {
        $brief = match ($kind) {
            EmailKind::WarmTease => 'A warm tease sent shortly after meeting the owner in person: remind them who we are in one line, mention one or two concrete things a custom solution could do for THEIR business (drawn from the context), and invite them to open their personal discovery link. Do not mention price.',
            EmailKind::FollowUp => 'A gentle follow-up to an owner who received their link but has not finished (or started) the discovery flow: one line of value they said they wanted, reassure them it takes a few relaxed minutes and saves progress, invite them to continue. Never guilt-trip.',
            EmailKind::ProposalCover => 'A cover email to accompany the attached project proposal: thank them for completing the discovery conversation, say the proposal reflects what they told us (name one or two of their own choices), and propose a short call to walk through it. Reference the attachment; do not restate the proposal.',
        };

        return <<<TASK
Email type: {$kind->value}. Write it in language "{$language->value}".

{$brief}

Return the {"subject": "...", "body": "..."} JSON object only.
TASK;
    }
}
