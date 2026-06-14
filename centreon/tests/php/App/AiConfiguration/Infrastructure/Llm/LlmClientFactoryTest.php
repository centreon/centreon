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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Infrastructure\Llm\AnthropicLlmClient;
use App\AiConfiguration\Infrastructure\Llm\LlmClientFactory;
use App\AiConfiguration\Infrastructure\Llm\MeteredLlmClient;
use App\AiConfiguration\Infrastructure\Llm\OpenAiCompatibleLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;
use Tests\App\Shared\Double\FakeHttpProxyProvider;

final class LlmClientFactoryTest extends TestCase
{
    private FakeAiProviderRepository $repository;

    private LlmClientFactory $factory;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $usageRepository = new FakeAiProviderUsageRepository();
        $this->factory = new LlmClientFactory(
            $this->repository,
            new MockHttpClient(),
            new FakeHttpProxyProvider(),
            $usageRepository,
            new BudgetStatusChecker($usageRepository, new MockClock()),
            $this->createMock(Security::class),
            new MockClock(),
        );
    }

    public function testAnthropicProviderGetsTheAnthropicClient(): void
    {
        $client = $this->factory->forProvider($this->createAiProvider(AiProviderTypeEnum::Anthropic));

        self::assertInstanceOf(MeteredLlmClient::class, $client);
        self::assertInstanceOf(AnthropicLlmClient::class, $client->inner);
    }

    public function testOtherProvidersGetTheOpenAiCompatibleClient(): void
    {
        foreach ([AiProviderTypeEnum::OpenAi, AiProviderTypeEnum::Mistral, AiProviderTypeEnum::Bedrock, AiProviderTypeEnum::OpenAiCompatible] as $type) {
            $client = $this->factory->forProvider($this->createAiProvider($type));

            self::assertInstanceOf(MeteredLlmClient::class, $client);
            self::assertInstanceOf(OpenAiCompatibleLlmClient::class, $client->inner);
        }
    }

    public function testDefaultProviderIsUsed(): void
    {
        $aiProvider = $this->createAiProvider(AiProviderTypeEnum::Anthropic, isActivated: true);
        $this->repository->add($aiProvider);

        $client = $this->factory->forActivatedProvider();

        self::assertInstanceOf(MeteredLlmClient::class, $client);
        self::assertInstanceOf(AnthropicLlmClient::class, $client->inner);
    }

    public function testMissingDefaultProviderThrows(): void
    {
        $this->expectException(LlmConfigurationException::class);

        $this->factory->forActivatedProvider();
    }

    public function testVaultStoredTokenIsNotResolvableYet(): void
    {
        $aiProvider = $this->createAiProvider(
            AiProviderTypeEnum::Anthropic,
            token: 'secret::hashicorp_vault::configuration/ai/uuid::_AI_API_TOKEN'
        );

        $this->expectException(LlmConfigurationException::class);

        $this->factory->forProvider($aiProvider);
    }

    private function createAiProvider(
        AiProviderTypeEnum $type,
        bool $isActivated = false,
        string $token = 'a-token',
    ): AiProvider {
        return new AiProvider(
            id: null,
            name: new AiProviderName(uniqid('provider-', true)),
            type: $type,
            endpointUrl: new EndpointUrl('https://api.example.org'),
            apiToken: new ApiToken($token),
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: $isActivated,
        );
    }
}
