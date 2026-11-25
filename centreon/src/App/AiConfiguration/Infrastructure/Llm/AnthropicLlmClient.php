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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the Anthropic Messages API (https://docs.anthropic.com).
 *
 * A model set to reason minimally is sent the least thinking it accepts. Its
 * thinking is never part of the answer: only the text blocks are, without a
 * leading <think> block.
 */
final readonly class AnthropicLlmClient implements LlmClientInterface
{
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const TIMEOUT_SECONDS = 120;
    private const MODEL_PAGE_SIZE = 1000;
    private const MAX_MODEL_PAGES = 10;

    private ReasoningNegotiator $reasoningNegotiator;

    public function __construct(
        private HttpClientInterface $httpClient,
        private EndpointUrl $endpointUrl,
        private ApiToken $apiToken,
        private ModelName $defaultModel,
        private ?ModelName $fastModel,
        // native Anthropic exposes the Messages API at /v1/messages; Bedrock
        // Mantle exposes the same API at /anthropic/v1/messages
        private string $messagesPath = '/v1/messages',
        private LoggerInterface $logger = new NullLogger(),
        private ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        // without a fast model, the default model serves the fast tier with its own setting
        private ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
        // remembers which reasoning setting each model accepts
        CacheInterface $cache = new ArrayAdapter(),
    ) {
        $this->reasoningNegotiator = new ReasoningNegotiator(
            $cache,
            $logger,
            'anthropic ' . $this->messagesUrl(),
            ReasoningCandidate::anthropicThinking(),
        );
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        [$response, $reasoning] = $this->send($request, stream: false);
        $data = $this->decodeOrFail($response, $this->timeoutFor($request));

        if (! is_array($data['content'] ?? null)) {
            // e.g. {} or {"error": ...} with a success status: not an answer
            throw LlmRequestFailedException::fromInvalidResponse(
                ProviderPayload::errorMessage($data, $this->apiToken) ?? 'no content in the response.'
            );
        }

        $content = '';
        foreach ($data['content'] as $block) {
            $block = ProviderPayload::array($block);
            // thinking and redacted_thinking blocks are the model's reasoning, not its answer
            if (($block['type'] ?? null) === 'text') {
                $content .= ProviderPayload::string($block['text'] ?? null) ?? '';
            }
        }
        $stopReason = ProviderPayload::string($data['stop_reason'] ?? null);
        $usage = ProviderPayload::array($data['usage'] ?? null);

        return new LlmResponse(
            content: ThinkBlockFilter::strip($content),
            model: ProviderPayload::string($data['model'] ?? null) ?? '',
            usage: new LlmUsage(
                inputTokens: ProviderPayload::tokenCount($usage['input_tokens'] ?? null),
                outputTokens: ProviderPayload::tokenCount($usage['output_tokens'] ?? null),
            ),
            truncated: $stopReason === 'max_tokens',
            refusal: $this->refusalOf($stopReason),
            reasoning: $reasoning?->label,
        );
    }

    public function stream(LlmRequest $request): \Generator
    {
        $timeout = $this->timeoutFor($request);
        // a rejected reasoning setting comes with the status, before any chunk
        [$response, $reasoning] = $this->send($request, stream: true);

        $parser = new ServerSentEventParser();
        $thinkBlockFilter = new ThinkBlockFilter();
        $content = '';
        $model = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $stopReason = null;

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                foreach ($parser->push($chunk->getContent()) as $payload) {
                    try {
                        $event = ProviderPayload::array(json_decode($payload, true, 512, \JSON_THROW_ON_ERROR));
                    } catch (\JsonException) {
                        // a malformed event is skipped
                        $event = [];
                    }
                    $delta = ProviderPayload::array($event['delta'] ?? null);

                    switch ($event['type'] ?? null) {
                        case 'message_start':
                            $message = ProviderPayload::array($event['message'] ?? null);
                            $usage = ProviderPayload::array($message['usage'] ?? null);
                            $model = ProviderPayload::string($message['model'] ?? null) ?? $model;
                            if (isset($usage['input_tokens'])) {
                                $inputTokens = ProviderPayload::tokenCount($usage['input_tokens']);
                            }
                            break;
                        case 'content_block_delta':
                            // thinking_delta and signature_delta carry the reasoning, not the answer
                            $text = ($delta['type'] ?? null) === 'text_delta'
                                ? $thinkBlockFilter->push(ProviderPayload::string($delta['text'] ?? null) ?? '')
                                : '';
                            if ($text !== '') {
                                $content .= $text;

                                yield $text;
                            }
                            break;
                        case 'message_delta':
                            $usage = ProviderPayload::array($event['usage'] ?? null);
                            if (isset($usage['output_tokens'])) {
                                $outputTokens = ProviderPayload::tokenCount($usage['output_tokens']);
                            }
                            $stopReason = ProviderPayload::string($delta['stop_reason'] ?? null) ?? $stopReason;
                            break;
                        case 'error':
                            throw LlmRequestFailedException::fromInvalidResponse(
                                ProviderPayload::errorMessage($event, $this->apiToken) ?? 'unknown streaming error'
                            );
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
            $stopReason === 'max_tokens',
            $this->refusalOf($stopReason),
            $reasoning?->label,
        );
    }

    public function listModels(): array
    {
        $ids = [];
        $afterId = null;
        // the list is paginated: follow it, but never endlessly
        for ($page = 0; $page < self::MAX_MODEL_PAGES; ++$page) {
            $query = ['limit' => self::MODEL_PAGE_SIZE];
            if ($afterId !== null) {
                $query['after_id'] = $afterId;
            }

            try {
                $response = $this->httpClient->request(
                    'GET',
                    rtrim($this->endpointUrl->value, '/') . '/v1/models',
                    [
                        'headers' => [
                            'anthropic-version' => self::ANTHROPIC_VERSION,
                            'x-api-key' => $this->apiToken->value,
                        ],
                        'query' => $query,
                        'timeout' => self::TIMEOUT_SECONDS,
                        // the provider's answer is reported back: never follow it elsewhere
                        'max_redirects' => 0,
                    ]
                );
            } catch (TransportExceptionInterface $exception) {
                throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
            }

            $data = $this->decodeOrFail($response, self::TIMEOUT_SECONDS);

            foreach (ProviderPayload::array($data['data'] ?? null) as $model) {
                $id = ProviderPayload::string(ProviderPayload::array($model)['id'] ?? null);
                if ($id !== null && $id !== '') {
                    $ids[] = $id;
                }
            }

            $afterId = ProviderPayload::string($data['last_id'] ?? null);
            if (($data['has_more'] ?? false) !== true || $afterId === null || $afterId === '') {
                break;
            }
        }
        sort($ids);

        return array_values(array_unique($ids));
    }

    private function refusalOf(?string $stopReason): ?string
    {
        // the Messages API reports a refusal as a stop reason, without its cause
        return $stopReason === 'refusal' ? 'the model declined to answer this request.' : null;
    }

    /**
     * Send the request, with the thinking setting the model accepts when it is set to
     * reason minimally.
     *
     * @throws LlmRequestFailedException when the request fails or the provider answers with an error status
     *
     * @return array{ResponseInterface, ReasoningCandidate|null} the successful response, and the
     *                                                           reasoning setting it was sent with
     */
    private function send(LlmRequest $request, bool $stream): array
    {
        $model = $request->model ?? $this->resolveTierModel($request->tier);

        return $this->reasoningNegotiator->send(
            $model,
            $this->resolveReasoning($request, $model),
            function (?ReasoningCandidate $reasoning) use ($request, $model, $stream): ResponseInterface {
                $response = $this->sendRequest($request, $model, $reasoning, $stream);
                $this->assertSuccessful($response, $this->timeoutFor($request));

                return $response;
            },
        );
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
        $payload = [
            'max_tokens' => ReasoningNegotiator::maxTokensFor($request->maxTokens, $reasoning),
            'messages' => array_map(
                static fn (LlmMessage $message): array => [
                    'content' => $message->content,
                    'role' => $message->role->value,
                ],
                $request->messages
            ),
            'model' => $model->value,
            ...($reasoning instanceof ReasoningCandidate ? $reasoning->payload : []),
        ];
        if ($request->systemPrompt !== null) {
            $payload['system'] = $request->systemPrompt;
        }
        if ($stream) {
            $payload['stream'] = true;
        }

        try {
            return $this->httpClient->request('POST', $this->messagesUrl(), [
                'headers' => [
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'x-api-key' => $this->apiToken->value,
                ],
                'json' => $payload,
                'timeout' => $this->timeoutFor($request),
                'max_redirects' => 0,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }
    }

    private function messagesUrl(): string
    {
        return rtrim($this->endpointUrl->value, '/') . $this->messagesPath;
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
}
