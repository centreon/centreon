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

use App\AiConfiguration\Application\Command\DiscoverModelsCommand;
use App\AiConfiguration\Application\Command\DiscoverModelsCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\Shared\Application\Command\NonTransactionalCommand;
use PHPUnit\Framework\TestCase;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClientFactory;

final class DiscoverModelsCommandHandlerTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
    }

    public function testReturnsTheModelsWithTheTypedToken(): void
    {
        $client = new FakeLlmClient();
        $client->models = ['claude-haiku-4-5', 'claude-sonnet-4-6'];

        $handler = $this->handler($client);

        $result = $handler($this->command(apiToken: new ApiToken('typed-token')));

        self::assertSame(['claude-haiku-4-5', 'claude-sonnet-4-6'], $result->models);
    }

    public function testUsesTheStoredTokenWhenNoneIsTyped(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $client = new FakeLlmClient();
        $handler = $this->handler($client);

        $result = $handler($this->command(apiToken: null, id: $provider->id()->value));

        self::assertSame(['fake-model-a', 'fake-model-b'], $result->models);
    }

    public function testTheConnectionSpecCarriesTheTargetAndTheToken(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $factory = new FakeLlmClientFactory(new FakeLlmClient());
        $handler = new DiscoverModelsCommandHandler($this->repository, $factory);

        $handler($this->command(apiToken: null, id: $provider->id()->value));

        $spec = $factory->lastConnectionSpec;
        self::assertNotNull($spec);
        self::assertSame(AiProviderTypeEnum::Anthropic, $spec->type);
        self::assertSame('https://api.anthropic.com', $spec->endpointUrl->value);
        self::assertSame('initial-token', $spec->apiToken?->value);
        self::assertNull($spec->model);
    }

    public function testRefusesTheStoredTokenForAnotherEndpoint(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $handler = $this->handler(new FakeLlmClient());

        $result = $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            endpointUrl: 'https://llm.example.com',
        ));

        // the fake client would have listed models had it been called
        self::assertSame([], $result->models);
        self::assertNotNull($result->error);
    }

    public function testRefusesTheStoredTokenForAnotherType(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $handler = $this->handler(new FakeLlmClient());

        $result = $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            type: AiProviderTypeEnum::Mistral,
            endpointUrl: 'https://api.mistral.ai',
        ));

        // the fake client would have listed models had it been called
        self::assertSame([], $result->models);
        self::assertNotNull($result->error);
    }

    public function testAServerThatMayNeedNoTokenIsAskedWithoutTheStoredOne(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $factory = new FakeLlmClientFactory(new FakeLlmClient());
        $handler = new DiscoverModelsCommandHandler($this->repository, $factory);

        $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: 'http://ollama.internal:11434',
        ));

        self::assertNotNull($factory->lastConnectionSpec);
        self::assertNull($factory->lastConnectionSpec->apiToken);
    }

    public function testNoTokenAndNoIdReturnsNoModels(): void
    {
        $handler = $this->handler(new FakeLlmClient());

        $result = $handler($this->command(apiToken: null));

        self::assertSame([], $result->models);
    }

    public function testProviderErrorIsReturnedAsDataNotThrown(): void
    {
        // an expected provider rejection is returned as data, so the processor
        // can map it to a 422 with its reason
        $handler = $this->handler(new FakeLlmClient(
            exception: LlmRequestFailedException::fromProviderError(401, 'invalid token')
        ));

        $result = $handler($this->command(apiToken: new ApiToken('bad-token')));

        self::assertSame([], $result->models);
        self::assertSame(
            'The AI provider rejected the request (HTTP 401): invalid token',
            $result->error
        );
    }

    public function testAnUnavailableVaultTokenPropagates(): void
    {
        // a platform failure, not a provider answer: it must be mapped and logged as such
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $handler = new DiscoverModelsCommandHandler(
            $this->repository,
            new FakeLlmClientFactory(exception: ApiTokenUnavailableException::fromVault())
        );

        $this->expectException(ApiTokenUnavailableException::class);

        $handler($this->command(apiToken: null, id: $provider->id()->value));
    }

    public function testTheCommandIsNotTransactional(): void
    {
        // no transaction may stay open during the provider call
        self::assertTrue(new \ReflectionClass(DiscoverModelsCommand::class)->implementsInterface(NonTransactionalCommand::class));
    }

    private function handler(FakeLlmClient $client): DiscoverModelsCommandHandler
    {
        return new DiscoverModelsCommandHandler(
            $this->repository,
            new FakeLlmClientFactory($client)
        );
    }

    private function command(
        ?ApiToken $apiToken,
        ?int $id = null,
        string $endpointUrl = 'https://api.anthropic.com',
        AiProviderTypeEnum $type = AiProviderTypeEnum::Anthropic,
    ): DiscoverModelsCommand {
        return new DiscoverModelsCommand(
            type: $type,
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: $apiToken,
            id: $id !== null ? new AiProviderId($id) : null,
        );
    }

    private function aiProvider(): AiProvider
    {
        return new AiProvider(
            id: null,
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new StoredApiToken('initial-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
        );
    }
}
