<?php

namespace App\Services\Ai\Tools\Email;

use App\Enums\EmailKind;
use App\Enums\Language;
use App\Models\BusinessOwner;
use App\Models\EmailDraft;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiClient;
use Illuminate\Support\Str;

/**
 * The email.generate tool (§6.5/§7.2, stage param = EmailKind): admin click
 * → one email_drafts row with subject + body in the requested language.
 * Returns null on a failed/gated call or an unparseable envelope — the admin
 * just clicks again; drafts are cheap and append-only.
 */
class EmailGenerator
{
    public function __construct(
        private readonly AiClient $aiClient,
        private readonly EmailAssembler $assembler,
    ) {}

    public function generate(BusinessOwner $businessOwner, EmailKind $kind, Language $language): ?EmailDraft
    {
        $result = $this->aiClient->call(AiCallRequest::fromContextBlocks(
            tool: 'email.generate',
            blocks: $this->assembler->assemble($businessOwner, $kind, $language),
            businessOwner: $businessOwner,
        ));

        if (! $result->successful || $result->text === null) {
            return null;
        }

        $parsed = $this->parse($result->text);

        if ($parsed === null) {
            return null;
        }

        return EmailDraft::create([
            'business_owner_id' => $businessOwner->id,
            'kind' => $kind,
            'language' => $language->value,
            'subject' => $parsed['subject'],
            'body' => $parsed['body'],
            'model_meta' => [
                'model' => $result->aiCall->model,
                'prompt_version' => 1,
                'ai_call_id' => $result->aiCall->id,
            ],
        ]);
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private function parse(string $text): ?array
    {
        $json = Str::of($text)->trim()->replaceMatches('/^```(?:json)?\s*|\s*```$/', '')->toString();

        $decoded = json_decode($json, true);

        if (! is_array($decoded)
            || ! is_string($decoded['subject'] ?? null) || trim($decoded['subject']) === ''
            || ! is_string($decoded['body'] ?? null) || trim($decoded['body']) === '') {
            return null;
        }

        return ['subject' => trim($decoded['subject']), 'body' => trim($decoded['body'])];
    }
}
