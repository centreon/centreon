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
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\Shared\Domain\VaultInterface;
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
        private VaultInterface $vault,
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
        $apiToken = $this->resolveToken($aiProvider->apiToken);
        $httpClient = $this->httpClient();

        $openAiCompatible = fn (): OpenAiCompatibleLlmClient => new OpenAiCompatibleLlmClient(
            $httpClient,
            $aiProvider->endpointUrl,
            $apiToken,
            $aiProvider->defaultModel,
            $aiProvider->fastModel,
            $aiProvider->type->chatCompletionsPath(),
            $aiProvider->type->chatMaxTokensField(),
        );

        // Gemini chats through the OpenAI-compatible transport but lists models
        // on its native endpoint (see GeminiLlmClient). Bedrock's Mantle endpoint
        // is multi-protocol: discovery and non-Anthropic families are OpenAI-
        // compatible, but Claude models need the Anthropic Messages API
        // (/anthropic/v1/messages) — see BedrockLlmClient.
        return match ($aiProvider->type) {
            AiProviderTypeEnum::Anthropic => new AnthropicLlmClient(
                $httpClient,
                $aiProvider->endpointUrl,
                $apiToken,
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
            ),
            AiProviderTypeEnum::Google => new GeminiLlmClient(
                $openAiCompatible(),
                $httpClient,
                $aiProvider->endpointUrl,
                $apiToken,
            ),
            AiProviderTypeEnum::Bedrock => new BedrockLlmClient(
                $openAiCompatible(),
                new AnthropicLlmClient(
                    $httpClient,
                    $aiProvider->endpointUrl,
                    $apiToken,
                    $aiProvider->defaultModel,
                    $aiProvider->fastModel,
                    '/anthropic/v1/messages',
                ),
                $aiProvider->defaultModel,
                $aiProvider->fastModel,
            ),
            AiProviderTypeEnum::OpenAi,
            AiProviderTypeEnum::Mistral,
            AiProviderTypeEnum::OpenAiCompatible => $openAiCompatible(),
        };
    }

    /**
     * A token stored in the vault is kept in the database as a "secret::..." reference, read back
     * here for each client. Users cannot submit such a reference themselves (rejected at input),
     * so it always designates the provider's own vault entry.
     *
     * @throws LlmConfigurationException when the reference cannot be read back (vault disabled,
     *                                   unreachable, or the entry is gone)
     */
    private function resolveToken(ApiToken $apiToken): ApiToken
    {
        if (! $apiToken->isStoredInVault()) {
            return $apiToken;
        }

        try {
            return new ApiToken($this->vault->resolve($apiToken->value));
        } catch (\Throwable $exception) {
            throw LlmConfigurationException::unresolvableToken($exception);
        }
    }

    /**
     * The proxy configured on the platform applies to all outbound LLM requests.
     */
    private function httpClient(): HttpClientInterface
    {
        $proxyUrl = $this->proxyProvider->findProxyUrl();

        return $proxyUrl === null
            ? $this->httpClient
            : $this->httpClient->withOptions(['proxy' => $proxyUrl]);
    }
}
