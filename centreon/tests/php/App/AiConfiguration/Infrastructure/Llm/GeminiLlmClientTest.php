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
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use App\AiConfiguration\Infrastructure\Llm\GeminiLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;

final class GeminiLlmClientTest extends TestCase
{
    public function testListModelsKeepsChatModelsAndStripsThePrefix(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): MockResponse {
                self::assertSame('GET', $method);
                self::assertStringContainsString(
                    'https://generativelanguage.googleapis.com/v1beta/models',
                    $url
                );

                return new MockResponse(json_encode([
                    'models' => [
                        ['name' => 'models/gemini-3.5-flash', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
                        ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
                        ['name' => 'models/gemini-3.1-flash-lite', 'supportedGenerationMethods' => ['generateContent']],
                    ],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $client = new GeminiLlmClient(
            new FakeLlmClient(),
            $httpClient,
            new EndpointUrl('https://generativelanguage.googleapis.com'),
            new ApiToken('a-key'),
        );

        // embedding model dropped; chat ids returned without the models/ prefix
        self::assertSame(
            ['gemini-3.1-flash-lite', 'gemini-3.5-flash'],
            $client->listModels()
        );
    }

    public function testCompleteDelegatesToTheOpenAiCompatibleClient(): void
    {
        $delegate = new FakeLlmClient(
            response: new LlmResponse('pong', 'gemini-3.5-flash', new LlmUsage(1, 1))
        );

        $client = new GeminiLlmClient(
            $delegate,
            new MockHttpClient(),
            new EndpointUrl('https://generativelanguage.googleapis.com'),
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
