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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Usage\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\Shared\Infrastructure\Proxy\HttpProxyProvider;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LlmClientFactory implements LlmClientFactoryInterface
{
    public function __construct(
        private AiProviderRepository $repository,
        private HttpClientInterface $httpClient,
        private HttpProxyProvider $proxyProvider,
        private AiProviderUsageRepository $usageRepository,
        private BudgetStatusChecker $budgetChecker,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    public function forActivatedProvider(): LlmClientInterface
    {
        $aiProvider = $this->repository->findActivated()
            ?? throw LlmConfigurationException::noActivatedProvider();

        return $this->forProvider($aiProvider);
    }

    public function forProvider(AiProvider $aiProvider): LlmClientInterface
    {
        // every call is metered, and capped when the provider has a spend limit
        return new MeteredLlmClient(
            $this->buildClient($aiProvider),
            $aiProvider,
            $this->usageRepository,
            $this->budgetChecker,
            $this->security,
            $this->clock,
        );
    }

    public function forConnectionTest(AiProvider $aiProvider): LlmClientInterface
    {
        // a connection test is a configuration-time check: it is not metered or
        // capped, and the provider may not be saved yet (no id to attribute to)
        return $this->buildClient($aiProvider);
    }

    private function buildClient(AiProvider $aiProvider): LlmClientInterface
    {
        if ($aiProvider->apiToken->isStoredInVault()) {
            // vault-backed token resolution is not wired into this container yet
            throw LlmConfigurationException::unresolvableToken();
        }

        $httpClient = $this->httpClient();

        return match ($aiProvider->type) {
            AiProviderTypeEnum::Anthropic => new AnthropicLlmClient(
                $httpClient,
                $aiProvider->endpointUrl,
                $aiProvider->apiToken,
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
            ),
            AiProviderTypeEnum::OpenAi,
            AiProviderTypeEnum::Mistral,
            AiProviderTypeEnum::Google,
            AiProviderTypeEnum::Bedrock,
            AiProviderTypeEnum::OpenAiCompatible => new OpenAiCompatibleLlmClient(
                $httpClient,
                $aiProvider->endpointUrl,
                $aiProvider->apiToken,
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
                $aiProvider->type->chatCompletionsPath(),
            ),
        };
    }

    /**
     * The proxy configured on the platform applies to all outbound LLM requests.
     */
    private function httpClient(): HttpClientInterface
    {
        $proxyUrl = $this->proxyProvider->getProxyUrl();

        return $proxyUrl === null
            ? $this->httpClient
            : $this->httpClient->withOptions(['proxy' => $proxyUrl]);
    }
}
