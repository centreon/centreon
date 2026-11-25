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
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Llm\ConnectionSpec;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\Shared\Domain\VaultInterface;
use App\Shared\Infrastructure\Proxy\HttpProxyProvider;
use App\Shared\Infrastructure\Proxy\ProxyUrl;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class LlmClientFactory implements LlmClientFactoryInterface
{
    /** Placeholder for the clients of a check that addresses no model (model discovery). */
    private const NO_MODEL = 'no-model';

    public function __construct(
        private AiProviderRepository $repository,
        #[Autowire(service: 'ai_configuration.http_client')]
        private HttpClientInterface $httpClient,
        #[Autowire(service: 'http_client')]
        private HttpClientInterface $unguardedHttpClient,
        private HttpProxyProvider $proxyProvider,
        private AiProviderUsageRepository $usageRepository,
        private BudgetStatusChecker $budgetChecker,
        private Security $security,
        private ClockInterface $clock,
        private VaultInterface $vault,
        private LoggerInterface $logger,
        private HostLookup $hostLookup,
        // remembers which reasoning setting each model accepts, across requests
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @throws LlmConfigurationException
     * @throws ApiTokenUnavailableException
     */
    public function forActivatedProvider(): LlmClientInterface
    {
        $aiProvider = $this->repository->findActivated()
            ?? throw LlmConfigurationException::noActivatedProvider();

        return $this->forProvider($aiProvider);
    }

    /**
     * @throws ApiTokenUnavailableException
     */
    public function forProvider(AiProvider $aiProvider): LlmClientInterface
    {
        // every call is metered, and capped when the provider has a spend limit
        return new MeteredLlmClient(
            $this->buildClient(
                $aiProvider->type(),
                $aiProvider->endpointUrl(),
                $aiProvider->apiToken(),
                $aiProvider->defaultModel(),
                $aiProvider->fastModel(),
                $aiProvider->defaultModelReasoning(),
                $aiProvider->fastModelReasoning(),
            ),
            $aiProvider,
            $this->usageRepository,
            $this->budgetChecker,
            $this->security,
            $this->clock,
        );
    }

    /**
     * @throws ApiTokenUnavailableException
     */
    public function forConnectionTest(ConnectionSpec $spec): LlmClientInterface
    {
        // a connection test is a configuration-time check: it is not metered or
        // capped, and the provider may not be saved yet (no id to attribute to)
        return $this->buildClient(
            $spec->type,
            $spec->endpointUrl,
            $spec->apiToken,
            $spec->model ?? new ModelName(self::NO_MODEL),
            null,
            $spec->modelReasoning,
            $spec->modelReasoning,
        );
    }

    /**
     * @throws ApiTokenUnavailableException
     */
    private function buildClient(
        AiProviderTypeEnum $type,
        EndpointUrl $endpointUrl,
        ?StoredApiToken $storedApiToken,
        ModelName $defaultModel,
        ?ModelName $fastModel,
        ReasoningModeEnum $defaultModelReasoning,
        ReasoningModeEnum $fastModelReasoning,
    ): LlmClientInterface {
        $apiToken = $storedApiToken instanceof StoredApiToken ? $this->getApiToken($storedApiToken) : null;
        if (! $apiToken instanceof ApiToken && $type->requiresApiToken()) {
            throw ApiTokenUnavailableException::missing();
        }
        // the clients of the types that need a token
        $requiredApiToken = static fn (): ApiToken => $apiToken ?? throw ApiTokenUnavailableException::missing();
        $httpClient = $this->httpClientFor($endpointUrl);

        $openAiCompatible = fn (): OpenAiCompatibleLlmClient => new OpenAiCompatibleLlmClient(
            $httpClient,
            $endpointUrl,
            $apiToken,
            $defaultModel,
            $fastModel,
            $type->chatCompletionsPath(),
            $type->chatMaxTokensField(),
            $this->logger,
            $defaultModelReasoning,
            $fastModelReasoning,
            $this->cache,
        );
        $anthropic = fn (string $messagesPath): AnthropicLlmClient => new AnthropicLlmClient(
            $httpClient,
            $endpointUrl,
            $requiredApiToken(),
            $defaultModel,
            $fastModel,
            $messagesPath,
            $this->logger,
            $defaultModelReasoning,
            $fastModelReasoning,
            $this->cache,
        );

        // Gemini and Bedrock mix transports: see GeminiLlmClient and BedrockLlmClient
        return match ($type) {
            AiProviderTypeEnum::Anthropic => $anthropic('/v1/messages'),
            AiProviderTypeEnum::Google => new GeminiLlmClient(
                $openAiCompatible(),
                $httpClient,
                $endpointUrl,
                $requiredApiToken(),
                $this->logger,
            ),
            AiProviderTypeEnum::Bedrock => new BedrockLlmClient(
                $openAiCompatible(),
                $anthropic('/anthropic/v1/messages'),
                $defaultModel,
                $fastModel,
            ),
            AiProviderTypeEnum::OpenAi,
            AiProviderTypeEnum::Mistral,
            AiProviderTypeEnum::OpenAiCompatible => $openAiCompatible(),
        };
    }

    /**
     * A token stored in the vault is kept in the database as a "secret::..." reference, read back
     * here for each client. A user can not submit such a reference (an ApiToken refuses it), so it
     * always designates the provider's own vault entry.
     *
     * @throws ApiTokenUnavailableException when the reference cannot be read back (vault disabled,
     *                                      unreachable, or the entry is gone)
     */
    private function getApiToken(StoredApiToken $storedApiToken): ApiToken
    {
        if (! $storedApiToken->isVaultReference()) {
            return $storedApiToken->plainToken();
        }

        try {
            return new ApiToken($this->vault->resolve($storedApiToken->value));
        } catch (\Throwable $exception) {
            throw ApiTokenUnavailableException::fromVault($exception);
        }
    }

    /**
     * The proxy configured on the platform applies to the outbound LLM requests,
     * except to a provider on the local network (e.g. Ollama or vLLM on a LAN
     * address), which a proxy usually can not reach.
     */
    private function httpClientFor(EndpointUrl $endpointUrl): HttpClientInterface
    {
        if (LocalNetworkHost::isLocal($endpointUrl->value)) {
            return $this->httpClient;
        }

        $proxyUrl = $this->proxyProvider->findProxyUrl();
        if (! $proxyUrl instanceof ProxyUrl) {
            return $this->httpClient;
        }

        if ($proxyUrl->vaultPasswordSkipped) {
            $this->logger->warning(
                'The proxy password is stored in the vault, which this request can not read: '
                . 'the AI provider is reached through the proxy without authentication'
            );
        }

        $proxy = ['proxy' => $proxyUrl->value];
        $host = mb_strtolower(trim((string) parse_url($endpointUrl->value, \PHP_URL_HOST), '[]'));
        if (filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            return $this->httpClient->withOptions($proxy);
        }

        // One lookup: its answer is handed to the guard, which then checks it
        // instead of resolving the host again.
        $address = $this->hostLookup->findIpv4Address($host);
        if ($address !== null) {
            return $this->httpClient->withOptions([...$proxy, 'resolve' => [$host => $address]]);
        }

        // an IPv6-only host: the guard looks it up itself
        if ($this->hostLookup->hasIpv6Address($host)) {
            return $this->httpClient->withOptions($proxy);
        }

        // The proxy resolves the target itself: a host the platform can not resolve
        // (no outside DNS) would be refused by the guard, so it goes to the proxy unchecked.
        $this->logger->info('The AI provider host does not resolve on the platform: it is resolved by the proxy, unchecked', [
            'host' => $host,
        ]);

        return $this->unguardedHttpClient->withOptions($proxy);
    }
}
