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
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google Gemini. Chat completions go through the OpenAI-compatible layer (the
 * delegate); model discovery uses Gemini's native ListModels, which—unlike the
 * OpenAI-compatible /models—reports each model's supported generation methods,
 * so embedding/image/TTS models can be filtered out.
 */
final class GeminiLlmClient implements LlmClientInterface
{
    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly LlmClientInterface $delegate,
        private readonly HttpClientInterface $httpClient,
        private readonly EndpointUrl $endpointUrl,
        private readonly ApiToken $apiToken,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        return $this->delegate->complete($request);
    }

    public function stream(LlmRequest $request): \Generator
    {
        return yield from $this->delegate->stream($request);
    }

    public function listModels(): array
    {
        $url = rtrim($this->endpointUrl->value, '/') . '/v1beta/models?pageSize=1000';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['x-goog-api-key' => $this->apiToken->value],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        if ($statusCode >= 400) {
            throw LlmRequestFailedException::fromProviderError($statusCode, 'model discovery failed');
        }

        try {
            /** @var array{models?: array<array{name?: string, supportedGenerationMethods?: array<string>}>} $data */
            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            throw LlmRequestFailedException::fromInvalidResponse($exception->getMessage());
        }

        $ids = [];
        foreach ($data['models'] ?? [] as $model) {
            // keep only chat models; the OpenAI-compatible chat layer addresses
            // them without the "models/" prefix the native API returns
            if (! in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true)) {
                continue;
            }
            $name = $model['name'] ?? '';
            $id = str_starts_with($name, 'models/') ? mb_substr($name, 7) : $name;
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        sort($ids);

        return array_values(array_unique($ids));
    }
}
