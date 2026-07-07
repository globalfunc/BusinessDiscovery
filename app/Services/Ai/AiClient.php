<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Messages\Message;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\OutputConfig\Effort;
use Anthropic\Messages\TextBlock;
use App\Enums\AiCallStatus;
use App\Models\AiCall;
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
 */
class AiClient
{
    private readonly Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(apiKey: config('ai.api_key'));
    }

    public function call(AiCallRequest $request): AiCallResult
    {
        $model = $request->model ?? $this->toolConfig($request->tool, 'model') ?? config('ai.default_model');
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

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
