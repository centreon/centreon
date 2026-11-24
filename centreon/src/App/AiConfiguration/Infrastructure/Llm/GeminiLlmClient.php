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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Google Gemini. Chat completions go through the OpenAI-compatible layer (the
 * delegate); model discovery uses Gemini's native ListModels, which—unlike the
 * OpenAI-compatible /models—reports each model's supported generation methods,
 * so embedding/image/TTS models can be filtered out.
 */
final readonly class GeminiLlmClient implements LlmClientInterface
{
    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private LlmClientInterface $delegate,
        private HttpClientInterface $httpClient,
        private EndpointUrl $endpointUrl,
        private ApiToken $apiToken,
        private LoggerInterface $logger = new NullLogger(),
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
                // the provider's answer is reported back: never follow it elsewhere
                'max_redirects' => 0,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TimeoutExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTimeout(self::TIMEOUT_SECONDS, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        if ($statusCode >= 400) {
            throw LlmRequestFailedException::fromProviderError(
                $statusCode,
                $this->extractErrorMessage($response, $statusCode)
            );
        }

        try {
            $data = $response->toArray();
        } catch (TimeoutExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTimeout(self::TIMEOUT_SECONDS, $exception);
        } catch (HttpClientExceptionInterface $exception) {
            throw LlmRequestFailedException::fromInvalidResponse($exception->getMessage(), $exception);
        }

        $ids = [];
        foreach (ProviderPayload::array($data['models'] ?? null) as $model) {
            $model = ProviderPayload::array($model);
            // keep only chat models; the OpenAI-compatible chat layer addresses
            // them without the "models/" prefix the native API returns
            if (! in_array('generateContent', ProviderPayload::array($model['supportedGenerationMethods'] ?? null), true)) {
                continue;
            }
            $name = ProviderPayload::string($model['name'] ?? null) ?? '';
            $id = str_starts_with($name, 'models/') ? mb_substr($name, 7) : $name;
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * Google reports errors as {"error": {"message": ...}}; any other body is
     * not reported (it may come from any server on the way).
     */
    private function extractErrorMessage(ResponseInterface $response, int $statusCode): string
    {
        try {
            $body = $response->getContent(false);
        } catch (HttpClientExceptionInterface) {
            return 'model discovery failed';
        }
        ProviderPayload::logErrorResponse($this->logger, $statusCode, $body);

        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        return ProviderPayload::errorMessage($data) ?? 'model discovery failed';
    }
}
