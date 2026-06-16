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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Amazon Bedrock. Chat completions go through the OpenAI-compatible mantle
 * endpoint (the delegate); model discovery uses the control-plane
 * ListInferenceProfiles, which returns the geography-scoped inference-profile
 * ids (us./eu./apac./global.) that chat actually uses, and which are
 * text-generation profiles. The Bedrock API key authenticates it too (no
 * SigV4), given the bedrock:ListInferenceProfiles permission.
 */
final class BedrockLlmClient implements LlmClientInterface
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
        // the chat endpoint is bedrock-mantle.<region>.api.aws; the control
        // plane that lists inference profiles is bedrock.<region>.amazonaws.com
        if (! preg_match('/bedrock-(?:mantle|runtime)\.([^.]+)\./', $this->endpointUrl->value, $matches)) {
            throw LlmRequestFailedException::fromInvalidResponse(
                'could not derive the AWS region from the Bedrock endpoint'
            );
        }
        $url = sprintf('https://bedrock.%s.amazonaws.com/inference-profiles?maxResults=1000', $matches[1]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'auth_bearer' => $this->apiToken->value,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw LlmRequestFailedException::fromTransportError($exception->getMessage(), $exception);
        }

        if ($statusCode >= 400) {
            throw LlmRequestFailedException::fromProviderError($statusCode, $this->extractErrorMessage($response));
        }

        try {
            /** @var array{inferenceProfileSummaries?: array<array{inferenceProfileId?: string}>} $data */
            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $exception) {
            throw LlmRequestFailedException::fromInvalidResponse($exception->getMessage());
        }

        $ids = [];
        foreach ($data['inferenceProfileSummaries'] ?? [] as $profile) {
            $id = $profile['inferenceProfileId'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }
        sort($ids);

        return array_values(array_unique($ids));
    }

    /**
     * Surface the AWS error so the cause is actionable. The control plane
     * returns {"Message": "..."} with a human-readable reason (e.g.
     * "Authentication failed: Please make sure your API Key is valid."), which
     * is used as-is. Only when no such message is present do we fall back to
     * the machine error type from the x-amzn-errortype header (e.g.
     * "AccessDeniedException:http://internal...") and then to a raw snippet.
     */
    private function extractErrorMessage(ResponseInterface $response): string
    {
        try {
            $body = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (HttpClientExceptionInterface) {
            return 'model discovery failed';
        }

        /** @var array{Message?: string, message?: string}|null $data */
        $data = json_decode($body, true);
        $message = is_array($data) ? ($data['Message'] ?? $data['message'] ?? null) : null;
        if (is_string($message) && $message !== '') {
            return $message;
        }

        $errorType = explode(':', $headers['x-amzn-errortype'][0] ?? '')[0];
        if ($errorType !== '') {
            return $errorType;
        }

        return $body !== '' ? mb_substr($body, 0, 200) : 'model discovery failed';
    }
}
