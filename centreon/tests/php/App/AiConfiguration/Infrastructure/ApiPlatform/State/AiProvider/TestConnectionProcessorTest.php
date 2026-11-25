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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class TestConnectionProcessorTest extends AiProviderApiTestCase
{
    private const TEST_ENDPOINT = self::ENDPOINT . '/test-connection';

    public function testUnauthenticatedReturns401(): void
    {
        $this->request('POST', self::TEST_ENDPOINT, [
            'json' => ['type' => 'anthropic', 'default_model' => 'claude-sonnet-4-6', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('POST', self::TEST_ENDPOINT, [
            'json' => ['type' => 'anthropic', 'default_model' => 'claude-sonnet-4-6', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOpenAiCompatibleWithoutEndpointReturns422(): void
    {
        $this->login();

        $this->request('POST', self::TEST_ENDPOINT, [
            'json' => ['type' => 'openai_compatible', 'default_model' => 'a-model', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testReusesTheStoredTokenOfAnExistingProvider(): void
    {
        $this->login();
        $id = $this->createProvider('Anthropic production');

        // no token typed: the stored token of the given provider is used; the
        // provider id travels as provider_id (id is reserved by the API)
        $response = $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'anthropic',
                'default_model' => 'claude-sonnet-4-6',
                'provider_id' => $id,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('success', $response->toArray());
    }

    public function testUnreachableEndpointIsReportedAsFailedTest(): void
    {
        $this->login();

        // no service listens on port 1: the test request can not succeed
        $response = $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'default_model' => 'a-model',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        /** @var array{success: bool, message: string|null} $result */
        $result = $response->toArray();
        self::assertFalse($result['success']);
        self::assertNotEmpty($result['message']);
    }

    public function testTheReasoningSettingOfTheModelIsAccepted(): void
    {
        $this->login();

        // no service listens on port 1: the test fails, and so sends no setting
        $response = $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'default_model' => 'a-model',
                'default_model_reasoning' => 'default',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://127.0.0.1:1',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        /** @var array{success: bool, reasoning?: string|null} $result */
        $result = $response->toArray();
        self::assertFalse($result['success']);
        self::assertNull($result['reasoning'] ?? null);
    }

    public function testASuccessfulTestSendsTheChosenSettingAndReportsTheOneApplied(): void
    {
        $factory = new FakeLlmClientFactory(new FakeLlmClient(
            new LlmResponse('pong', 'a-model', new LlmUsage(1, 1), reasoning: 'reasoning_effort: low')
        ));
        self::getContainer()->set(LlmClientFactoryInterface::class, $factory);
        $this->login();

        $response = $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'default_model' => 'a-model',
                'default_model_reasoning' => 'default',
                'api_token' => 'a-token',
                'endpoint_url' => 'https://llm.example.com/v1',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(ReasoningModeEnum::Default, $factory->lastConnectionSpec?->modelReasoning);
        /** @var array{success: bool, reasoning?: string|null} $result */
        $result = $response->toArray();
        self::assertTrue($result['success']);
        self::assertSame('reasoning_effort: low', $result['reasoning'] ?? null);
    }

    public function testAnUnknownReasoningSettingIsRejected(): void
    {
        $this->login();

        $response = $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'anthropic',
                'default_model' => 'a-model',
                'default_model_reasoning' => 'high',
                'api_token' => 'a-token',
            ],
        ]);

        self::assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testUnknownTypeReturns400(): void
    {
        $this->login();

        $this->request('POST', self::TEST_ENDPOINT, [
            'json' => ['type' => 'unknown', 'default_model' => 'a-model', 'api_token' => 'a-token'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testBedrockWithAnotherEndpointReturns422(): void
    {
        $this->login();

        $this->request('POST', self::TEST_ENDPOINT, [
            'json' => [
                'type' => 'bedrock',
                'default_model' => 'a-model',
                'api_token' => 'a-token',
                'endpoint_url' => 'https://attacker.example.org',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
