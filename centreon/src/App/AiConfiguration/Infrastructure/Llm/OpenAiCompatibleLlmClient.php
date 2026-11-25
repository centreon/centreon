<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the OpenAI chat completions API and compatible providers
 * (OpenAI, Mistral, Ollama, vLLM, LiteLLM, ...).
 *
 * A model set to reason minimally is sent the lowest `reasoning_effort` it
 * accepts. Its reasoning is never part of the answer: the separate `reasoning`
 * or `reasoning_content` fields and the typed thinking parts are ignored, and a
 * leading <think> block is dropped.
 */
final readonly class OpenAiCompatibleLlmClient implements LlmClientInterface
{
    private const TIMEOUT_SECONDS = 120;

    private ReasoningNegotiator $reasoningNegotiator;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EndpointUrl $endpointUrl,
        // null for a server that needs none: no Authorization header is sent
        private ?ApiToken $apiToken,
        private ModelName $defaultModel,
        private ?ModelName $fastModel,
        // most providers expose chat completions at /v1/chat/completions;
        // some compatible APIs (e.g. Gemini) use a different path
        private string $chatCompletionsPath = '/v1/chat/completions',
        // OpenAI's recent models require 'max_completion_tokens' over 'max_tokens'
        private string $maxTokensField = 'max_tokens',
        private LoggerInterface $logger = new NullLogger(),
        private ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        // without a fast model, the default model serves the fast tier with its own setting
        private ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
        // remembers which reasoning setting each model accepts
        CacheItemPoolInterface $cache = new ArrayAdapter(),
    ) {
        $this->reasoningNegotiator = new ReasoningNegotiator(
            $cache,
            $logger,
            'openai_compatible ' . $this->chatCompletionsUrl(),
            ReasoningCandidate::reasoningEffort(),
        );
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        [$response, $reasoning, $reasoningMode] = $this->send($request, stream: false);
        $data = $this->decodeOrFail($response, $this->timeoutFor($request));

        $choice = ProviderPayload::array(ProviderPayload::array($data['choices'] ?? null)[0] ?? null);
        $message = $choice['message'] ?? null;
        if (! is_array($message)) {
            // e.g. {} or {"error": ...} with a success status: not an answer
            throw LlmRequestFailedException::fromInvalidResponse(
                ProviderPayload::errorMessage($data, $this->apiToken) ?? 'no completion in the response.'
            );
        }

        $finishReason = ProviderPayload::string($choice['finish_reason'] ?? null);
        $usage = ProviderPayload::array($data['usage'] ?? null);

        return new LlmResponse(
            content: ThinkBlockFilter::stripWithOpeningTagInPrompt($this->textOf($message['content'] ?? null)),
            model: ProviderPayload::string($data['model'] ?? null) ?? '',
            usage: new LlmUsage(
                inputTokens: ProviderPayload::tokenCount($usage['prompt_tokens'] ?? null),
                outputTokens: ProviderPayload::tokenCount($usage['completion_tokens'] ?? null),
            ),
            truncated: $finishReason === 'length',
            refusal: $this->refusalOf(ProviderPayload::string($message['refusal'] ?? null) ?? '', $finishReason),
            reasoning: $reasoning?->label,
            maxTokens: ReasoningNegotiator::maxTokensFor($request->maxTokens, $reasoning),
            reasoningMode: $reasoningMode,
        );
    }

    public function stream(LlmRequest $request): \Generator
    {
        $timeout = $this->timeoutFor($request);
        // a rejected reasoning setting comes with the status, before any chunk
        [$response, $reasoning, $reasoningMode] = $this->send($request, stream: true);

        $parser = new ServerSentEventParser();
        $thinkBlockFilter = new ThinkBlockFilter();
        $content = '';
        $model = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $truncated = false;
        $refusal = '';
        $finishReason = null;

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                foreach ($parser->push($chunk->getContent()) as $payload) {
                    if ($payload === '[DONE]') {
                        continue;
                    }

                    try {
                        $event = ProviderPayload::array(json_decode($payload, true, 512, \JSON_THROW_ON_ERROR));
                    } catch (\JsonException) {
                        // a malformed event is skipped
                        $event = [];
                    }

                    if (isset($event['error'])) {
                        throw LlmRequestFailedException::fromInvalidResponse(
                            ProviderPayload::errorMessage($event, $this->apiToken) ?? 'unknown streaming error'
                        );
                    }

                    $usage = ProviderPayload::array($event['usage'] ?? null);
                    $choice = ProviderPayload::array(ProviderPayload::array($event['choices'] ?? null)[0] ?? null);
                    $delta = ProviderPayload::array($choice['delta'] ?? null);

                    $model = ProviderPayload::string($event['model'] ?? null) ?? $model;
                    if (isset($usage['prompt_tokens'])) {
                        $inputTokens = ProviderPayload::tokenCount($usage['prompt_tokens']);
                    }
                    if (isset($usage['completion_tokens'])) {
                        $outputTokens = ProviderPayload::tokenCount($usage['completion_tokens']);
                    }
                    $finishReason = ProviderPayload::string($choice['finish_reason'] ?? null) ?? $finishReason;
                    if ($finishReason === 'length') {
                        $truncated = true;
                    }
                    $refusal .= ProviderPayload::string($delta['refusal'] ?? null) ?? '';

                    $text = $thinkBlockFilter->push($this->textOf($delta['content'] ?? null));
                    if ($text !== '') {
                        $content .= $text;

                        yield $text;
                    }
                }
            }
        } catch (TimeoutExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTimeout($timeout, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        $text = $thinkBlockFilter->finish();
        if ($text !== '') {
            $content .= $text;

            yield $text;
        }

        return new LlmResponse(
            $content,
            $model,
            new LlmUsage($inputTokens, $outputTokens),
            $truncated,
            $this->refusalOf($refusal, $finishReason),
            $reasoning?->label,
            ReasoningNegotiator::maxTokensFor($request->maxTokens, $reasoning),
            $reasoningMode,
        );
    }

    public function listModels(): array
    {
        // the models endpoint sits next to chat completions (/v1/models)
        $url = rtrim($this->endpointUrl->value, '/')
            . str_replace('/chat/completions', '/models', $this->chatCompletionsPath);

        try {
            $response = $this->httpClient->request('GET', $url, [
                ...$this->authentication(),
                'timeout' => self::TIMEOUT_SECONDS,
                // the provider's answer is reported back: never follow it elsewhere
                'max_redirects' => 0,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        $data = $this->decodeOrFail($response, self::TIMEOUT_SECONDS);

        $ids = [];
        foreach (ProviderPayload::array($data['data'] ?? null) as $model) {
            $model = ProviderPayload::array($model);
            $id = ProviderPayload::string($model['id'] ?? null);
            if ($id === null) {
                continue;
            }
            if ($id === '') {
                continue;
            }

            // a provider that advertises chat capability (e.g. Mistral) is
            // authoritative; otherwise (OpenAI and most compatible servers
            // expose no capability) drop ids that are clearly not chat models
            $chatCapability = ProviderPayload::array($model['capabilities'] ?? null)['completion_chat'] ?? null;
            if ($chatCapability === false) {
                continue;
            }
            if ($chatCapability === null && $this->looksNonChat($id)) {
                continue;
            }

            $ids[] = $id;
        }
        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * The content is a string, or—for some providers (e.g. Mistral reasoning
     * models)—a list of typed parts, of which only the text parts are the answer.
     */
    private function textOf(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return '';
        }

        $text = '';
        foreach ($content as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * A refusal is not an empty answer: it carries why the provider did not answer.
     */
    private function refusalOf(string $refusal, ?string $finishReason): ?string
    {
        if (trim($refusal) !== '') {
            return trim($refusal);
        }

        return $finishReason === 'content_filter' ? 'the answer was blocked by its content filter.' : null;
    }

    /**
     * Heuristic for the providers that expose no capability metadata: drop ids
     * that clearly name a non-chat model (embeddings, audio, image, …).
     */
    private function looksNonChat(string $id): bool
    {
        // audio is intentionally absent: gpt-4o-audio/gpt-audio do chat
        // completions. "instruct" is absent too: it is legacy-completions-only
        // at OpenAI but names plenty of chat models on compatible servers.
        $markers = [
            'embed', 'tts', 'whisper', 'dall-e', 'dalle', 'imagen', 'image',
            'sora', 'video', 'realtime', 'computer-use', 'moderation', 'rerank',
            'ocr', 'guardrail', 'stable-diffusion', 'canvas', 'davinci', 'babbage',
        ];
        $id = mb_strtolower($id);
        foreach ($markers as $marker) {
            if (str_contains($id, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send the request, with the reasoning setting the model accepts when it is set to
     * reason minimally.
     *
     * @throws LlmRequestFailedException when the request fails or the provider answers with an error status
     *
     * @return array{ResponseInterface, ReasoningCandidate|null, ReasoningModeEnum} the successful
     *                                                                              response, the
     *                                                                              reasoning setting it
     *                                                                              was sent with, and
     *                                                                              the mode of the model
     */
    private function send(LlmRequest $request, bool $stream): array
    {
        $model = $request->model ?? $this->resolveTierModel($request->tier);
        $mode = $this->resolveReasoning($request, $model);

        [$response, $reasoning] = $this->reasoningNegotiator->send(
            $model,
            $mode,
            function (?ReasoningCandidate $reasoning) use ($request, $model, $stream): ResponseInterface {
                $response = $this->sendRequest($request, $model, $reasoning, $stream);
                $this->assertSuccessful($response, $this->timeoutFor($request));

                return $response;
            },
        );

        return [$response, $reasoning, $mode];
    }

    /**
     * @throws LlmRequestFailedException when the request can not be sent
     */
    private function sendRequest(
        LlmRequest $request,
        ModelName $model,
        ?ReasoningCandidate $reasoning,
        bool $stream,
    ): ResponseInterface {
        $messages = array_map(
            static fn (LlmMessage $message): array => [
                'content' => $message->content,
                'role' => $message->role->value,
            ],
            $request->messages
        );
        if ($request->systemPrompt !== null) {
            array_unshift($messages, ['content' => $request->systemPrompt, 'role' => 'system']);
        }

        $payload = [
            $this->maxTokensField => ReasoningNegotiator::maxTokensFor($request->maxTokens, $reasoning),
            'messages' => $messages,
            'model' => $model->value,
            ...($reasoning instanceof ReasoningCandidate ? $reasoning->payload : []),
        ];
        if ($stream) {
            $payload['stream'] = true;
        }

        try {
            return $this->httpClient->request('POST', $this->chatCompletionsUrl(), [
                ...$this->authentication(),
                'json' => $payload,
                'timeout' => $this->timeoutFor($request),
                'max_redirects' => 0,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }
    }

    private function chatCompletionsUrl(): string
    {
        return rtrim($this->endpointUrl->value, '/') . $this->chatCompletionsPath;
    }

    private function timeoutFor(LlmRequest $request): int
    {
        return $request->timeoutSeconds ?? self::TIMEOUT_SECONDS;
    }

    private function resolveTierModel(LlmModelTierEnum $tier): ModelName
    {
        return match ($tier) {
            LlmModelTierEnum::Default => $this->defaultModel,
            LlmModelTierEnum::Fast => $this->fastModel ?? $this->defaultModel,
        };
    }

    /**
     * The setting of the tier the model serves; a model named by the request that
     * serves neither tier gets the setting of the default model.
     */
    private function resolveReasoning(LlmRequest $request, ModelName $model): ReasoningModeEnum
    {
        if (! $this->fastModel instanceof ModelName) {
            return $this->defaultModelReasoning;
        }

        $servesFastTier = $request->model instanceof ModelName
            ? $model->value === $this->fastModel->value
                && ($model->value !== $this->defaultModel->value || $request->tier === LlmModelTierEnum::Fast)
            : $request->tier === LlmModelTierEnum::Fast;

        return $servesFastTier ? $this->fastModelReasoning : $this->defaultModelReasoning;
    }

    /**
     * @param int $timeout the idle timeout of the request, reported if it is reached
     *
     * @throws LlmRequestFailedException when the provider can not be reached, times out or
     *                                   answers with an error status
     */
    private function assertSuccessful(ResponseInterface $response, int $timeout): void
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TimeoutExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTimeout($timeout, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        if ($statusCode >= 400) {
            throw LlmRequestFailedException::fromProviderError(
                $statusCode,
                $this->extractErrorMessage($response, $statusCode)
            );
        }
    }

    /**
     * @param int $timeout the idle timeout of the request, reported if it is reached
     *
     * @throws LlmRequestFailedException when the request fails or its body is not a JSON document
     *
     * @return array<mixed>
     */
    private function decodeOrFail(ResponseInterface $response, int $timeout): array
    {
        $this->assertSuccessful($response, $timeout);

        try {
            $data = $response->toArray();
        } catch (TimeoutExceptionInterface $exception) {
            // the headers arrived, but the body stalled (e.g. a model still reasoning)
            throw LlmRequestFailedException::fromTimeout($timeout, $exception);
        } catch (HttpClientExceptionInterface $exception) {
            throw LlmRequestFailedException::fromInvalidResponse($exception->getMessage(), $exception);
        }

        return $data;
    }

    private function extractErrorMessage(ResponseInterface $response, int $statusCode): string
    {
        try {
            $body = $response->getContent(false);
        } catch (HttpClientExceptionInterface) {
            return 'no response body';
        }
        ProviderPayload::logErrorResponse($this->logger, $statusCode, $body);

        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // not a JSON error document: its body is not reported (it may come from any server)
            $data = null;
        }

        return ProviderPayload::errorMessage($data, $this->apiToken) ?? 'no error message in the response';
    }

    /**
     * @return array{auth_bearer?: string}
     */
    private function authentication(): array
    {
        return $this->apiToken instanceof ApiToken ? ['auth_bearer' => $this->apiToken->value] : [];
    }
}
