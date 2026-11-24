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
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\ConnectionSpec;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Infrastructure\Llm\AnthropicLlmClient;
use App\AiConfiguration\Infrastructure\Llm\BedrockLlmClient;
use App\AiConfiguration\Infrastructure\Llm\GeminiLlmClient;
use App\AiConfiguration\Infrastructure\Llm\LlmClientFactory;
use App\AiConfiguration\Infrastructure\Llm\MeteredLlmClient;
use App\AiConfiguration\Infrastructure\Llm\OpenAiCompatibleLlmClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;
use Tests\App\Shared\Double\FakeHttpProxyProvider;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class LlmClientFactoryTest extends TestCase
{
    private const STORED_TOKEN = 'secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN';

    private FakeAiProviderRepository $repository;

    private FakeVault $vault;

    private LlmClientFactory $factory;

    protected function setUp(): void
    {
        $this->repository = new FakeAiProviderRepository();
        $this->vault = new FakeVault();
        $this->factory = $this->createFactory(new MockHttpClient());
    }

    public function testAnthropicProviderGetsTheAnthropicClient(): void
    {
        $client = $this->factory->forProvider($this->createAiProvider(AiProviderTypeEnum::Anthropic));

        self::assertInstanceOf(MeteredLlmClient::class, $client);
        self::assertInstanceOf(AnthropicLlmClient::class, $client->inner);
    }

    public function testOtherProvidersGetTheOpenAiCompatibleClient(): void
    {
        foreach ([AiProviderTypeEnum::OpenAi, AiProviderTypeEnum::Mistral, AiProviderTypeEnum::OpenAiCompatible] as $type) {
            $client = $this->factory->forProvider($this->createAiProvider($type));

            self::assertInstanceOf(MeteredLlmClient::class, $client);
            self::assertInstanceOf(OpenAiCompatibleLlmClient::class, $client->inner);
        }
    }

    public function testGoogleProviderGetsTheGeminiClient(): void
    {
        $client = $this->factory->forProvider($this->createAiProvider(AiProviderTypeEnum::Google));

        self::assertInstanceOf(MeteredLlmClient::class, $client);
        self::assertInstanceOf(GeminiLlmClient::class, $client->inner);
    }

    public function testBedrockProviderGetsTheBedrockClient(): void
    {
        $client = $this->factory->forProvider($this->createAiProvider(AiProviderTypeEnum::Bedrock));

        self::assertInstanceOf(MeteredLlmClient::class, $client);
        self::assertInstanceOf(BedrockLlmClient::class, $client->inner);
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

    public function testAVaultStoredTokenIsReadBackFromTheVault(): void
    {
        $this->vault->resolved[self::STORED_TOKEN] = 'the-real-token';

        $sentKeys = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$sentKeys): MockResponse {
                /** @var array{normalized_headers: array<string, list<string>>} $options */
                $sentKeys = $options['normalized_headers']['x-api-key'] ?? [];

                return new MockResponse(json_encode([
                    'content' => [['type' => 'text', 'text' => 'pong']],
                    'model' => 'claude-sonnet-5-5',
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $this->createFactory($httpClient)
            ->forConnectionTest(new ConnectionSpec(
                AiProviderTypeEnum::Anthropic,
                new EndpointUrl('https://api.anthropic.com'),
                new StoredApiToken(self::STORED_TOKEN),
                new ModelName('claude-sonnet-5-5'),
            ))
            ->complete(new LlmRequest(
                messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
                feature: AiFeatureEnum::ConnectionTest,
                maxTokens: 16,
            ));

        // the provider receives the token itself, never the vault reference
        self::assertSame(['x-api-key: the-real-token'], $sentKeys);
    }

    public function testAnUnresolvableVaultTokenIsAPlatformError(): void
    {
        $this->vault->resolveThrows = true;

        // not a configuration problem the user can fix in the form: the vault failed
        $this->expectException(ApiTokenUnavailableException::class);

        $this->factory->forProvider($this->createAiProvider(AiProviderTypeEnum::Anthropic, token: self::STORED_TOKEN));
    }

    public function testRequestsGoThroughThePlatformProxy(): void
    {
        self::assertSame(
            'http://proxy.example.org:3128',
            $this->proxyOfARequest(new FakeHttpProxyProvider('http://proxy.example.org:3128'))
        );
    }

    public function testRequestsGoDirectWithoutAPlatformProxy(): void
    {
        self::assertNull($this->proxyOfARequest(new FakeHttpProxyProvider()));
    }

    public function testAPublicCompatibleEndpointGoesThroughThePlatformProxy(): void
    {
        self::assertSame(
            'http://proxy.example.org:3128',
            $this->proxyOfAConnectionTest('https://llm.example.org', new FakeHttpProxyProvider('http://proxy.example.org:3128'))
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLocalNetworkEndpoints(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1:11434'];

        yield 'private' => ['http://10.0.0.12:8000'];

        yield 'localhost' => ['http://localhost:11434'];

        yield 'IPv6 unique local' => ['http://[fd12:3456::1]:8000'];
    }

    #[DataProvider('provideLocalNetworkEndpoints')]
    public function testALocalNetworkEndpointBypassesThePlatformProxy(string $endpointUrl): void
    {
        self::assertNull(
            $this->proxyOfAConnectionTest($endpointUrl, new FakeHttpProxyProvider('http://proxy.example.org:3128'))
        );
    }

    public function testAProxyUsedWithoutItsVaultPasswordIsLogged(): void
    {
        $logger = new RecordingLogger();

        $this->proxyOfARequest(
            new FakeHttpProxyProvider('http://proxy.example.org:3128', vaultPasswordSkipped: true),
            $logger
        );

        self::assertCount(1, $logger->records());
        self::assertSame('warning', $logger->records()[0]['level']);
    }

    public function testAProxyWithItsCredentialsIsNotLogged(): void
    {
        $logger = new RecordingLogger();

        $this->proxyOfARequest(new FakeHttpProxyProvider('http://user:pass@proxy.example.org:3128'), $logger);

        self::assertSame([], $logger->records());
    }

    public function testAConnectionTestOfACuratedProviderTargetsItsOfficialEndpoint(): void
    {
        $urls = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url) use (&$urls): MockResponse {
                $urls[] = $url;

                return new MockResponse('{"data":[],"has_more":false}');
            }
        );

        $this->createFactory($httpClient)
            ->forConnectionTest(new ConnectionSpec(
                AiProviderTypeEnum::Anthropic,
                new EndpointUrl('https://elsewhere.example.org'),
                new StoredApiToken('a-token'),
            ))
            ->listModels();

        self::assertSame(['https://api.anthropic.com/v1/models?limit=1000'], $urls);
    }

    #[DataProvider('provideProxiedTargets')]
    public function testAProxiedTargetIsCheckedOnlyWhenItResolvesLocally(string $endpointUrl, bool $guarded): void
    {
        $used = [];
        $client = static function (string $name) use (&$used): MockHttpClient {
            return new MockHttpClient(
                static function () use (&$used, $name): MockResponse {
                    $used[] = $name;

                    return new MockResponse('{"choices":[{"message":{"content":"pong"}}],"model":"llama3"}');
                }
            );
        };

        $this->createFactory(
            $client('guarded'),
            new FakeHttpProxyProvider('http://proxy.example.org:3128'),
            unguardedHttpClient: $client('unguarded'),
        )
            ->forConnectionTest(new ConnectionSpec(
                AiProviderTypeEnum::OpenAiCompatible,
                new EndpointUrl($endpointUrl),
                new StoredApiToken('a-token'),
                new ModelName('llama3'),
            ))
            ->complete(LlmRequest::connectionTest(new ModelName('llama3')));

        self::assertSame([$guarded ? 'guarded' : 'unguarded'], $used);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideProxiedTargets(): iterable
    {
        yield 'an IP address' => ['https://203.0.113.10', true];

        yield 'a host the platform resolves' => ['https://localhost', true];

        yield 'a host only the proxy can resolve' => ['https://llm.invalid', false];
    }

    /**
     * Sends a metered request and returns the proxy option the HTTP client received.
     */
    private function proxyOfARequest(FakeHttpProxyProvider $proxyProvider, ?LoggerInterface $logger = null): ?string
    {
        $sentProxy = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$sentProxy): MockResponse {
                $sentProxy = $options['proxy'] ?? null;

                return new MockResponse(json_encode([
                    'content' => [['type' => 'text', 'text' => 'pong']],
                    'model' => 'claude-sonnet-5-5',
                    'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $aiProvider = $this->createAiProvider(AiProviderTypeEnum::Anthropic, isActivated: true);
        $this->repository->add($aiProvider);
        $this->createFactory($httpClient, $proxyProvider, $logger)
            ->forActivatedProvider()
            ->complete(new LlmRequest(
                messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
                feature: AiFeatureEnum::ResourceExplanation,
                maxTokens: 16,
            ));

        /** @var string|null $sentProxy */
        return $sentProxy;
    }

    /**
     * Tests an OpenAI-compatible endpoint and returns the proxy option the HTTP client received.
     */
    private function proxyOfAConnectionTest(string $endpointUrl, FakeHttpProxyProvider $proxyProvider): ?string
    {
        $sentProxy = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$sentProxy): MockResponse {
                $sentProxy = $options['proxy'] ?? null;

                return new MockResponse('{"choices":[{"message":{"content":"pong"}}],"model":"llama3"}');
            }
        );

        $this->createFactory($httpClient, $proxyProvider)
            ->forConnectionTest(new ConnectionSpec(
                AiProviderTypeEnum::OpenAiCompatible,
                new EndpointUrl($endpointUrl),
                new StoredApiToken('a-token'),
                new ModelName('llama3'),
            ))
            ->complete(LlmRequest::connectionTest(new ModelName('llama3')));

        /** @var string|null $sentProxy */
        return $sentProxy;
    }

    private function createFactory(
        HttpClientInterface $httpClient,
        ?FakeHttpProxyProvider $proxyProvider = null,
        ?LoggerInterface $logger = null,
        ?HttpClientInterface $unguardedHttpClient = null,
    ): LlmClientFactory {
        $usageRepository = new FakeAiProviderUsageRepository();

        return new LlmClientFactory(
            $this->repository,
            $httpClient,
            $unguardedHttpClient ?? $httpClient,
            $proxyProvider ?? new FakeHttpProxyProvider(),
            $usageRepository,
            new BudgetStatusChecker($usageRepository, new MockClock()),
            $this->securityWithoutUser(),
            new MockClock(),
            $this->vault,
            $logger ?? new NullLogger(),
        );
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
            endpointUrl: $type->defaultEndpointUrl() ?? new EndpointUrl(
                $type === AiProviderTypeEnum::Bedrock ? 'https://bedrock-mantle.us-east-1.api.aws' : 'https://api.example.org'
            ),
            apiToken: new StoredApiToken($token),
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: $isActivated,
        );
    }

    /**
     * A real Security whose token storage holds no token: no logged-in user.
     */
    private function securityWithoutUser(): Security
    {
        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());

        return new Security($container);
    }
}
