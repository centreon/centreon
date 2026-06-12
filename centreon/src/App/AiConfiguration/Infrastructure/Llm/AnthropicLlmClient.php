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
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the Anthropic Messages API (https://docs.anthropic.com).
 */
final class AnthropicLlmClient implements LlmClientInterface
{
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EndpointUrl $endpointUrl,
        private readonly ApiToken $apiToken,
        private readonly ?ModelName $defaultModel,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $response = $this->sendRequest($request, stream: false);

        /** @var array{
         *   content?: array<array{type?: string, text?: string}>,
         *   model?: string,
         *   usage?: array{input_tokens?: int, output_tokens?: int},
         * } $data */
        $data = $this->decodeOrFail($response);

        $content = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $content .= $block['text'] ?? '';
            }
        }

        return new LlmResponse(
            content: $content,
            model: $data['model'] ?? '',
            usage: new LlmUsage(
                inputTokens: $data['usage']['input_tokens'] ?? 0,
                outputTokens: $data['usage']['output_tokens'] ?? 0,
            ),
        );
    }

    public function stream(LlmRequest $request): \Generator
    {
        $response = $this->sendRequest($request, stream: true);
        $this->assertSuccessful($response);

        $parser = new ServerSentEventParser();
        $content = '';
        $model = '';
        $inputTokens = 0;
        $outputTokens = 0;

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                foreach ($parser->push($chunk->getContent()) as $payload) {
                    /** @var array{
                     *   type?: string,
                     *   message?: array{model?: string, usage?: array{input_tokens?: int}},
                     *   delta?: array{type?: string, text?: string},
                     *   usage?: array{output_tokens?: int},
                     *   error?: array{message?: string},
                     * } $event */
                    $event = json_decode($payload, true) ?? [];

                    switch ($event['type'] ?? null) {
                        case 'message_start':
                            $model = $event['message']['model'] ?? $model;
                            $inputTokens = $event['message']['usage']['input_tokens'] ?? $inputTokens;
                            break;
                        case 'content_block_delta':
                            if (($event['delta']['type'] ?? null) === 'text_delta') {
                                $text = $event['delta']['text'] ?? '';
                                $content .= $text;

                                yield $text;
                            }
                            break;
                        case 'message_delta':
                            $outputTokens = $event['usage']['output_tokens'] ?? $outputTokens;
                            break;
                        case 'error':
                            throw LlmRequestFailedException::fromInvalidResponse(
                                $event['error']['message'] ?? 'unknown streaming error'
                            );
                    }
                }
            }
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        return new LlmResponse($content, $model, new LlmUsage($inputTokens, $outputTokens));
    }

    private function sendRequest(LlmRequest $request, bool $stream): ResponseInterface
    {
        $model = $request->model ?? $this->defaultModel ?? throw LlmConfigurationException::noModel();

        $payload = [
            'max_tokens' => $request->maxTokens,
            'messages' => array_map(
                static fn (LlmMessage $message): array => [
                    'content' => $message->content,
                    'role' => $message->role->value,
                ],
                $request->messages
            ),
            'model' => $model->value,
        ];
        if ($request->systemPrompt !== null) {
            $payload['system'] = $request->systemPrompt;
        }
        if ($stream) {
            $payload['stream'] = true;
        }

        try {
            return $this->httpClient->request('POST', rtrim($this->endpointUrl->value, '/') . '/v1/messages', [
                'headers' => [
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'x-api-key' => $this->apiToken->value,
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }
    }

    private function assertSuccessful(ResponseInterface $response): void
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        if ($statusCode >= 400) {
            throw LlmRequestFailedException::fromProviderError(
                $statusCode,
                $this->extractErrorMessage($response)
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOrFail(ResponseInterface $response): array
    {
        $this->assertSuccessful($response);

        try {
            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            throw LlmRequestFailedException::fromInvalidResponse($exception->getMessage());
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function extractErrorMessage(ResponseInterface $response): string
    {
        try {
            $body = $response->getContent(false);
        } catch (HttpClientExceptionInterface) {
            return 'no response body';
        }

        /** @var array{error?: array{message?: string}}|null $data */
        $data = json_decode($body, true);

        return $data['error']['message'] ?? mb_substr($body, 0, 200);
    }
}
