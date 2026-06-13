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

namespace Tests\App\AiConfiguration\Application\Command;

use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommand;
use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class TestAiProviderConnectionCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
    }

    public function testSuccessWithTheTypedToken(): void
    {
        $handler = $this->handler(new FakeLlmClient(
            response: new LlmResponse('pong', 'claude-sonnet-4-6', new LlmUsage(5, 1))
        ));

        $result = $handler($this->command(apiToken: new ApiToken('typed-token')));

        self::assertTrue($result->success);
        self::assertSame('claude-sonnet-4-6', $result->model);
    }

    public function testFailureReportsTheProviderError(): void
    {
        $handler = $this->handler(new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        ));

        $result = $handler($this->command(apiToken: new ApiToken('bad-token')));

        self::assertFalse($result->success);
        self::assertNotNull($result->message);
    }

    public function testUsesTheStoredTokenWhenNoneIsTyped(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $client = new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1)));
        $handler = $this->handler($client);

        $result = $handler($this->command(apiToken: null, id: $provider->id()->value));

        self::assertTrue($result->success);
        // the stored provider was loaded and the client was called
        self::assertNotNull($client->lastRequest);
    }

    public function testNoTokenAndNoIdFails(): void
    {
        $handler = $this->handler(new FakeLlmClient());

        $result = $handler($this->command(apiToken: null));

        self::assertFalse($result->success);
    }

    private function handler(FakeLlmClient $client): TestAiProviderConnectionCommandHandler
    {
        return new TestAiProviderConnectionCommandHandler(
            $this->repository,
            new FakeLlmClientFactory($client)
        );
    }

    private function command(?ApiToken $apiToken, ?int $id = null): TestAiProviderConnectionCommand
    {
        return new TestAiProviderConnectionCommand(
            type: AiProviderTypeEnum::Anthropic,
            model: new ModelName('claude-sonnet-4-6'),
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: $apiToken,
            id: $id !== null ? new \App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId($id) : null,
        );
    }

    private function aiProvider(): AiProvider
    {
        return new AiProvider(
            id: null,
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new ApiToken('initial-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
        );
    }
}
