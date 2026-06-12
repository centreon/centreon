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

use App\AiConfiguration\Application\Command\TestAiProviderCommand;
use App\AiConfiguration\Application\Command\TestAiProviderCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class TestAiProviderCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    private AiProvider $aiProvider;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $this->aiProvider = new AiProvider(
            id: null,
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new ApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isDefault: true,
        );
        $this->repository->add($this->aiProvider);
    }

    public function testSuccessfulTestReportsTheModel(): void
    {
        $client = new FakeLlmClient(
            response: new LlmResponse('pong', 'claude-sonnet-4-6', new LlmUsage(12, 2))
        );
        $handler = new TestAiProviderCommandHandler($this->repository, new FakeLlmClientFactory($client));

        $result = $handler(new TestAiProviderCommand($this->aiProvider->id()));

        self::assertTrue($result->success);
        self::assertSame('claude-sonnet-4-6', $result->model);
        self::assertNull($result->message);
    }

    public function testModelOverrideIsForwardedToTheClient(): void
    {
        $client = new FakeLlmClient();
        $handler = new TestAiProviderCommandHandler($this->repository, new FakeLlmClientFactory($client));

        $handler(new TestAiProviderCommand($this->aiProvider->id(), new ModelName('claude-haiku-4-5')));

        self::assertSame('claude-haiku-4-5', $client->lastRequest?->model?->value);
    }

    public function testFailedRequestIsReportedAsUnsuccessful(): void
    {
        $client = new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid x-api-key')
        );
        $handler = new TestAiProviderCommandHandler($this->repository, new FakeLlmClientFactory($client));

        $result = $handler(new TestAiProviderCommand($this->aiProvider->id()));

        self::assertFalse($result->success);
        self::assertStringContainsString('invalid x-api-key', (string) $result->message);
    }

    public function testUnusableConfigurationIsReportedAsUnsuccessful(): void
    {
        $handler = new TestAiProviderCommandHandler(
            $this->repository,
            new FakeLlmClientFactory(exception: LlmConfigurationException::unresolvableToken())
        );

        $result = $handler(new TestAiProviderCommand($this->aiProvider->id()));

        self::assertFalse($result->success);
        self::assertStringContainsString('resolved', (string) $result->message);
    }

    public function testUnknownProviderThrows(): void
    {
        $handler = new TestAiProviderCommandHandler($this->repository, new FakeLlmClientFactory());

        $this->expectException(AiProviderNotFoundException::class);

        $handler(new TestAiProviderCommand(new AiProviderId(999999)));
    }
}
