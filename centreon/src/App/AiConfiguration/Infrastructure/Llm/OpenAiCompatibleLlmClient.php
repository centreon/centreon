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
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the OpenAI chat completions API and compatible providers
 * (OpenAI, Mistral, Ollama, vLLM, LiteLLM, ...).
 */
final class OpenAiCompatibleLlmClient implements LlmClientInterface
{
    private const TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EndpointUrl $endpointUrl,
        private readonly ApiToken $apiToken,
        private readonly ModelName $defaultModel,
        private readonly ?ModelName $fastModel,
        // most providers expose chat completions at /v1/chat/completions;
        // some compatible APIs (e.g. Gemini) use a different path
        private readonly string $chatCompletionsPath = '/v1/chat/completions',
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $response = $this->sendRequest($request, stream: false);

        /** @var array{
         *   model?: string,
         *   choices?: array<array{message?: array{content?: string|null}}>,
         *   usage?: array{prompt_tokens?: int, completion_tokens?: int},
         * } $data */
        $data = $this->decodeOrFail($response);

        return new LlmResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            model: $data['model'] ?? '',
            usage: new LlmUsage(
                inputTokens: $data['usage']['prompt_tokens'] ?? 0,
                outputTokens: $data['usage']['completion_tokens'] ?? 0,
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
                    if ($payload === '[DONE]') {
                        continue;
                    }

                    /** @var array{
                     *   model?: string,
                     *   choices?: array<array{delta?: array{content?: string|null}}>,
                     *   usage?: array{prompt_tokens?: int, completion_tokens?: int},
                     *   error?: array{message?: string},
                     * } $event */
                    $event = json_decode($payload, true) ?? [];

                    if (isset($event['error'])) {
                        throw LlmRequestFailedException::fromInvalidResponse(
                            $event['error']['message'] ?? 'unknown streaming error'
                        );
                    }

                    $model = $event['model'] ?? $model;
                    $inputTokens = $event['usage']['prompt_tokens'] ?? $inputTokens;
                    $outputTokens = $event['usage']['completion_tokens'] ?? $outputTokens;

                    $text = $event['choices'][0]['delta']['content'] ?? null;
                    if (is_string($text) && $text !== '') {
                        $content .= $text;

                        yield $text;
                    }
                }
            }
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        return new LlmResponse($content, $model, new LlmUsage($inputTokens, $outputTokens));
    }

    public function listModels(): array
    {
        // the models endpoint sits next to chat completions (/v1/models, or
        // /v1beta/openai/models for Gemini)
        $url = rtrim($this->endpointUrl->value, '/')
            . str_replace('/chat/completions', '/models', $this->chatCompletionsPath);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'auth_bearer' => $this->apiToken->value,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        /** @var array{data?: array<array{id?: string}>} $data */
        $data = $this->decodeOrFail($response);

        $ids = [];
        foreach ($data['data'] ?? [] as $model) {
            $id = $model['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        sort($ids);

        return array_values(array_unique($ids));
    }

    private function sendRequest(LlmRequest $request, bool $stream): ResponseInterface
    {
        $model = $request->model ?? $this->resolveTierModel($request->tier);

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
            'max_tokens' => $request->maxTokens,
            'messages' => $messages,
            'model' => $model->value,
        ];
        if ($stream) {
            $payload['stream'] = true;
        }

        try {
            return $this->httpClient->request('POST', rtrim($this->endpointUrl->value, '/') . $this->chatCompletionsPath, [
                'auth_bearer' => $this->apiToken->value,
                'json' => $payload,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }
    }

    private function resolveTierModel(LlmModelTierEnum $tier): ModelName
    {
        return match ($tier) {
            LlmModelTierEnum::Default => $this->defaultModel,
            LlmModelTierEnum::Fast => $this->fastModel ?? $this->defaultModel,
        };
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

        /** @var array{error?: array{message?: string}|string}|null $data */
        $data = json_decode($body, true);
        $error = $data['error'] ?? null;
        if (is_array($error)) {
            return $error['message'] ?? mb_substr($body, 0, 200);
        }

        return is_string($error) ? $error : mb_substr($body, 0, 200);
    }
}
