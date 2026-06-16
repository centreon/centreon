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

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use App\AiConfiguration\Infrastructure\Llm\BedrockLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;

final class BedrockLlmClientTest extends TestCase
{
    public function testListModelsReturnsInferenceProfileIdsFromTheRegionControlPlane(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): MockResponse {
                self::assertSame('GET', $method);
                // the region is derived from the mantle endpoint, on the control plane
                self::assertStringStartsWith(
                    'https://bedrock.eu-west-1.amazonaws.com/inference-profiles',
                    $url
                );

                return new MockResponse(json_encode([
                    'inferenceProfileSummaries' => [
                        ['inferenceProfileId' => 'eu.anthropic.claude-sonnet-4-6'],
                        ['inferenceProfileId' => 'eu.anthropic.claude-haiku-4-5'],
                    ],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $client = new BedrockLlmClient(
            new FakeLlmClient(),
            $httpClient,
            new EndpointUrl('https://bedrock-mantle.eu-west-1.api.aws'),
            new ApiToken('a-key'),
        );

        self::assertSame(
            ['eu.anthropic.claude-haiku-4-5', 'eu.anthropic.claude-sonnet-4-6'],
            $client->listModels()
        );
    }

    public function testListModelsSurfacesTheHumanAwsMessageWithoutTheErrorType(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(
                '{"Message":"Authentication failed: Please make sure your API Key is valid."}',
                [
                    'http_code' => 403,
                    'response_headers' => [
                        'x-amzn-errortype: AccessDeniedException:http://internal.amazon.com/coral/',
                    ],
                ]
            )
        );

        $client = new BedrockLlmClient(
            new FakeLlmClient(),
            $httpClient,
            new EndpointUrl('https://bedrock-mantle.eu-west-1.api.aws'),
            new ApiToken('a-key'),
        );

        // the human message stands on its own; the AccessDeniedException type is
        // not prefixed when a message is present
        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage(
            'The AI provider rejected the request (HTTP 403): Authentication failed: Please make sure your API Key is valid.'
        );

        $client->listModels();
    }

    public function testListModelsFallsBackToTheErrorTypeWhenThereIsNoMessage(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(
                '',
                [
                    'http_code' => 403,
                    'response_headers' => [
                        'x-amzn-errortype: AccessDeniedException:http://internal.amazon.com/coral/',
                    ],
                ]
            )
        );

        $client = new BedrockLlmClient(
            new FakeLlmClient(),
            $httpClient,
            new EndpointUrl('https://bedrock-mantle.eu-west-1.api.aws'),
            new ApiToken('a-key'),
        );

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage(
            'The AI provider rejected the request (HTTP 403): AccessDeniedException'
        );

        $client->listModels();
    }

    public function testCompleteDelegatesToTheOpenAiCompatibleClient(): void
    {
        $delegate = new FakeLlmClient(
            response: new LlmResponse('pong', 'eu.anthropic.claude-sonnet-4-6', new LlmUsage(1, 1))
        );

        $client = new BedrockLlmClient(
            $delegate,
            new MockHttpClient(),
            new EndpointUrl('https://bedrock-mantle.eu-west-1.api.aws'),
            new ApiToken('a-key'),
        );

        $response = $client->complete(new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ConnectionTest,
        ));

        self::assertSame('pong', $response->content);
        self::assertNotNull($delegate->lastRequest);
    }
}
