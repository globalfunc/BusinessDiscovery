<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Messages\Message;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\OutputConfig\Effort;
use Anthropic\Messages\TextBlock;
use App\Enums\AiCallStatus;
use App\Models\AiCall;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Server-side wrapper around the Anthropic Messages API (§7.1). Every call —
 * regardless of tool — goes through call(), which logs an `ai_calls` row
 * with token/cost/latency/status. Concrete tools (S3.1+) build an
 * AiCallRequest (directly, or via AiCallRequest::fromContextBlocks()) and
 * pass it here; this class never throws past its own boundary — a failed
 * request is reported and returned as an unsuccessful AiCallResult so a
 * caller's UI can fall back gracefully (§7.7).
 *
 * The §7.6 vendor-neutrality output filter is applied here, once, for every
 * tool — so DCP, the four suggest.* tools, and any future text-emitting tool
 * inherit it without per-tool wiring. On a blocklist hit, call() regenerates
 * once with a corrective turn; a second hit is redacted and flagged
 * vendor_leak on its ai_calls row for admin review.
 */
class AiClient
{
    private readonly Client $client;

    private readonly VendorFilter $vendorFilter;

    private readonly BudgetGate $budgetGate;

    public function __construct(?Client $client = null, ?VendorFilter $vendorFilter = null, ?BudgetGate $budgetGate = null)
    {
        $this->client = $client ?? new Client(apiKey: config('ai.api_key'));
        $this->vendorFilter = $vendorFilter ?? app(VendorFilter::class);
        $this->budgetGate = $budgetGate ?? app(BudgetGate::class);
    }

    /**
     * Run the §7.7 pre-flight gate (rate limit, then token budget), then
     * dispatch and run the §7.6 vendor filter. Callers see a single
     * AiCallResult whose text is already vendor-safe; the gate/regeneration/
     * redaction are invisible to them, preserving the never-block contract —
     * every tool already treats an unsuccessful result as its cue to fall
     * back (presets / empty DCP / deterministic spec renderer), so a gated
     * call needs no special handling downstream.
     */
    public function call(AiCallRequest $request): AiCallResult
    {
        $blockedStatus = $this->budgetGate->check($request);

        if ($blockedStatus !== null) {
            return $this->blocked($request, $blockedStatus);
        }

        $result = $this->dispatch($request);

        if (! $result->successful || $result->text === null) {
            return $result;
        }

        $matches = $this->vendorFilter->scan($result->text);

        if ($matches === []) {
            return $result;
        }

        // First hit → one automatic regeneration with a corrective instruction.
        $retry = $this->dispatch($this->vendorFilter->correctiveRequest($request, $result->text, $matches));

        if ($retry->successful && $retry->text !== null) {
            $retryMatches = $this->vendorFilter->scan($retry->text);

            if ($retryMatches === []) {
                return $retry;
            }

            // Second hit → redact + log vendor_leak on the regenerated call.
            return $this->redactAndFlag($retry, $retryMatches);
        }

        // Regeneration failed to produce usable text: never block — redact the
        // first attempt and flag it so output stays vendor-safe and parseable.
        return $this->redactAndFlag($result, $matches);
    }

    /**
     * Redact matched terms to their generic labels and mark the call's
     * ai_calls row vendor_leak=true for admin review (§7.6.2).
     *
     * @param  VendorMatch[]  $matches
     */
    private function redactAndFlag(AiCallResult $result, array $matches): AiCallResult
    {
        $result->aiCall->update(['vendor_leak' => true]);

        Log::warning('Vendor leak persisted after regeneration; redacted for admin review.', [
            'ai_call_id' => $result->aiCall->id,
            'tool' => $result->aiCall->tool,
            'terms' => $this->vendorFilter->leakedTerms($matches),
        ]);

        return new AiCallResult(
            successful: true,
            text: $this->vendorFilter->redact($result->text ?? '', $matches),
            aiCall: $result->aiCall,
        );
    }

    /**
     * A gated call never reaches the API — still logged (§7.7 "all
     * prompts/outputs logged for audit") with zero tokens/cost so usage
     * queries and the admin usage explorer (S4.8) can distinguish it from a
     * real attempt.
     */
    private function blocked(AiCallRequest $request, AiCallStatus $status): AiCallResult
    {
        $aiCall = AiCall::create([
            'business_owner_id' => $request->businessOwner?->id,
            'tool' => $request->tool,
            'model' => $this->resolveModel($request),
            'input_tokens' => 0,
            'output_tokens' => 0,
            'latency_ms' => 0,
            'cost_estimate' => null,
            'status' => $status,
        ]);

        return new AiCallResult(successful: false, text: null, aiCall: $aiCall);
    }

    /**
     * One raw round-trip: call the Messages API and log the ai_calls row.
     * Protected so the vendor-filter orchestration in call() can be tested
     * against a queued transport without a live API.
     */
    protected function dispatch(AiCallRequest $request): AiCallResult
    {
        $model = $this->resolveModel($request);
        $effort = $request->effort ?? $this->toolConfig($request->tool, 'effort') ?? config('ai.default_effort');
        $maxTokens = $request->maxTokens ?? $this->toolConfig($request->tool, 'max_tokens') ?? config('ai.default_max_tokens');
        $temperature = config('ai.default_temperature');

        $startedAt = microtime(true);

        try {
            $message = $this->client->messages->create(
                maxTokens: $maxTokens,
                messages: $request->messages,
                model: $model,
                system: $request->system,
                outputConfig: $effort ? OutputConfig::with(effort: Effort::from($effort)) : null,
                temperature: $temperature,
            );

            $aiCall = $this->log(
                request: $request,
                model: $model,
                inputTokens: $message->usage->inputTokens,
                outputTokens: $message->usage->outputTokens,
                latencyMs: $this->elapsedMs($startedAt),
                status: AiCallStatus::Success,
            );

            return new AiCallResult(
                successful: true,
                text: $this->extractText($message),
                aiCall: $aiCall,
            );
        } catch (Throwable $e) {
            $aiCall = $this->log(
                request: $request,
                model: $model,
                inputTokens: 0,
                outputTokens: 0,
                latencyMs: $this->elapsedMs($startedAt),
                status: AiCallStatus::Failed,
                errorMessage: Str::limit($e->getMessage(), 2000),
            );

            report($e);

            return new AiCallResult(successful: false, text: null, aiCall: $aiCall);
        }
    }

    private function extractText(Message $message): string
    {
        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        return $text;
    }

    private function log(
        AiCallRequest $request,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs,
        AiCallStatus $status,
        ?string $errorMessage = null,
    ): AiCall {
        return AiCall::create([
            'business_owner_id' => $request->businessOwner?->id,
            'tool' => $request->tool,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms' => $latencyMs,
            'cost_estimate' => $this->estimateCost($model, $inputTokens, $outputTokens),
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Reads the $/1M-token pricing table from config/ai.php (S3.6 and S4.8's
     * usage explorer read the same table — keep pricing changes there, not here).
     */
    private function estimateCost(string $model, int $inputTokens, int $outputTokens): ?float
    {
        $pricing = config("ai.pricing.{$model}");

        if (! $pricing) {
            return null;
        }

        return round(
            ($inputTokens / 1_000_000) * $pricing['input'] + ($outputTokens / 1_000_000) * $pricing['output'],
            6,
        );
    }

    private function toolConfig(string $tool, string $key): mixed
    {
        return config("ai.tools.{$tool}.{$key}");
    }

    private function resolveModel(AiCallRequest $request): string
    {
        return $request->model ?? $this->toolConfig($request->tool, 'model') ?? config('ai.default_model');
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
