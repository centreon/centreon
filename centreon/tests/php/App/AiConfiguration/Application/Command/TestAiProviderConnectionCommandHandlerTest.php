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

use App\AiConfiguration\Application\Command\ConnectionTestWarningEnum;
use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommand;
use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommandHandler;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use App\Shared\Application\Command\NonTransactionalCommand;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSendsAShortRequestThatNeedsNoAnswer(): void
    {
        $client = new FakeLlmClient();

        ($this->handler($client))($this->command(apiToken: new ApiToken('typed-token')));

        self::assertNotNull($client->lastRequest);
        self::assertSame(30, $client->lastRequest->timeoutSeconds);
        self::assertFalse($client->lastRequest->requiresAnswer);
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

    public function testTheConnectionSpecCarriesTheTargetTheTokenAndTheModel(): void
    {
        $factory = new FakeLlmClientFactory(new FakeLlmClient());
        $handler = new TestAiProviderConnectionCommandHandler($this->repository, $factory);

        $handler($this->command(apiToken: new ApiToken('typed-token')));

        $spec = $factory->lastConnectionSpec;
        self::assertNotNull($spec);
        self::assertSame(AiProviderTypeEnum::Anthropic, $spec->type);
        self::assertSame('https://api.anthropic.com', $spec->endpointUrl->value);
        self::assertSame('typed-token', $spec->apiToken?->value);
        self::assertSame('claude-sonnet-4-6', $spec->model?->value);
        self::assertSame(ReasoningModeEnum::Minimal, $spec->modelReasoning);
    }

    public function testTheReasoningSettingIsTestedAndReported(): void
    {
        $factory = new FakeLlmClientFactory(new FakeLlmClient(
            response: new LlmResponse('pong', 'claude-sonnet-4-6', new LlmUsage(5, 1), reasoning: 'thinking: disabled')
        ));
        $handler = new TestAiProviderConnectionCommandHandler($this->repository, $factory);

        $result = $handler($this->command(apiToken: new ApiToken('typed-token'), modelReasoning: ReasoningModeEnum::Default));

        self::assertSame(ReasoningModeEnum::Default, $factory->lastConnectionSpec?->modelReasoning);
        self::assertSame('thinking: disabled', $result->reasoning);
        self::assertNull($result->warning);
    }

    /**
     * @return iterable<string, array{LlmResponse, ConnectionTestWarningEnum|null}>
     */
    public static function provideWarnings(): iterable
    {
        $usage = new LlmUsage(5, 16);

        yield 'a setting accepted' => [
            new LlmResponse('pong', 'a-model', $usage, reasoning: 'reasoning_effort: none', reasoningMode: ReasoningModeEnum::Minimal),
            null,
        ];

        yield 'the model default' => [
            new LlmResponse('pong', 'a-model', $usage, reasoningMode: ReasoningModeEnum::Default),
            null,
        ];

        yield 'set to reason minimally, no setting accepted' => [
            new LlmResponse('pong', 'a-model', $usage, reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::NoReasoningSetting,
        ];

        yield 'the whole budget used without an answer' => [
            new LlmResponse(' ', 'a-model', $usage, truncated: true, reasoning: 'reasoning_effort: low', reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::BudgetUsedWithoutAnswer,
        ];

        yield 'no setting accepted and the whole budget used: the budget warning wins' => [
            new LlmResponse('', 'a-model', $usage, truncated: true, reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::BudgetUsedWithoutAnswer,
        ];

        yield 'an empty answer within the budget' => [
            new LlmResponse('', 'a-model', $usage, reasoning: 'reasoning_effort: none', reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::EmptyAnswer,
        ];

        yield 'no setting accepted and an empty answer: the empty answer wins' => [
            new LlmResponse('', 'a-model', $usage, reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::EmptyAnswer,
        ];

        yield 'a refusal, not an empty answer' => [
            new LlmResponse('', 'a-model', $usage, refusal: 'the answer was blocked by a content filter.', reasoningMode: ReasoningModeEnum::Minimal),
            ConnectionTestWarningEnum::Refused,
        ];

        yield 'a cut answer is still an answer' => [
            new LlmResponse('po', 'a-model', $usage, truncated: true, reasoningMode: ReasoningModeEnum::Default),
            null,
        ];
    }

    #[DataProvider('provideWarnings')]
    public function testASuccessfulTestWarnsAboutTheModel(LlmResponse $response, ?ConnectionTestWarningEnum $warning): void
    {
        $result = $this->handler(new FakeLlmClient(response: $response))($this->command(apiToken: new ApiToken('typed-token')));

        self::assertTrue($result->success);
        self::assertSame($warning, $result->warning);
        // the reason of a refusal is the only message of a successful test
        self::assertSame($response->refusal, $result->message);
    }

    public function testARefusalStillProvesTheConnection(): void
    {
        $handler = $this->handler(new FakeLlmClient(
            response: new LlmResponse('', 'claude-sonnet-4-6', new LlmUsage(5, 1), refusal: 'declined')
        ));

        $result = $handler($this->command(apiToken: new ApiToken('typed-token')));

        self::assertTrue($result->success);
    }

    public function testRefusesTheStoredTokenForAnotherEndpoint(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $client = new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1)));
        $handler = $this->handler($client);

        $result = $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            endpointUrl: 'https://llm.example.com',
        ));

        self::assertFalse($result->success);
        self::assertNull($client->lastRequest);
    }

    public function testRefusesTheStoredTokenForAnotherType(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $client = new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1)));
        $handler = $this->handler($client);

        $result = $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            type: AiProviderTypeEnum::Mistral,
            endpointUrl: 'https://api.mistral.ai',
        ));

        self::assertFalse($result->success);
        self::assertNull($client->lastRequest);
    }

    public function testAServerThatMayNeedNoTokenIsTestedWithoutTheStoredOne(): void
    {
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $factory = new FakeLlmClientFactory(new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1))));
        $handler = new TestAiProviderConnectionCommandHandler($this->repository, $factory);

        $result = $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: 'http://ollama.internal:11434',
        ));

        self::assertTrue($result->success);
        self::assertNotNull($factory->lastConnectionSpec);
        self::assertNull($factory->lastConnectionSpec->apiToken);
    }

    public function testTheStoredTokenOfAGatewayIsStillUsedOnItsOwnEndpoint(): void
    {
        $provider = new AiProvider(
            id: null,
            name: new AiProviderName('Gateway'),
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: new EndpointUrl('https://llm-gateway.example.com'),
            apiToken: new StoredApiToken('gateway-token'),
            defaultModel: new ModelName('a-model'),
            fastModel: null,
            description: null,
            isActivated: false,
        );
        $this->repository->add($provider);
        $factory = new FakeLlmClientFactory(new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1))));
        $handler = new TestAiProviderConnectionCommandHandler($this->repository, $factory);

        $handler($this->command(
            apiToken: null,
            id: $provider->id()->value,
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: 'https://llm-gateway.example.com',
        ));

        self::assertSame('gateway-token', $factory->lastConnectionSpec?->apiToken?->value);
    }

    public function testANewServerThatMayNeedNoTokenIsTestedWithoutOne(): void
    {
        $factory = new FakeLlmClientFactory(new FakeLlmClient(response: new LlmResponse('pong', 'm', new LlmUsage(5, 1))));
        $handler = new TestAiProviderConnectionCommandHandler($this->repository, $factory);

        $result = $handler($this->command(
            apiToken: null,
            type: AiProviderTypeEnum::OpenAiCompatible,
            endpointUrl: 'http://ollama.internal:11434',
        ));

        self::assertTrue($result->success);
        self::assertNull($factory->lastConnectionSpec?->apiToken);
    }

    public function testNoTokenAndNoIdFails(): void
    {
        $handler = $this->handler(new FakeLlmClient());

        $result = $handler($this->command(apiToken: null));

        self::assertFalse($result->success);
    }

    public function testAnUnavailableVaultTokenPropagates(): void
    {
        // a platform failure, not a provider answer: it must be mapped and logged as such
        $provider = $this->aiProvider();
        $this->repository->add($provider);
        $handler = new TestAiProviderConnectionCommandHandler(
            $this->repository,
            new FakeLlmClientFactory(exception: ApiTokenUnavailableException::fromVault())
        );

        $this->expectException(ApiTokenUnavailableException::class);

        $handler($this->command(apiToken: null, id: $provider->id()->value));
    }

    public function testTheCommandIsNotTransactional(): void
    {
        // no transaction may stay open during the provider call
        self::assertTrue(new \ReflectionClass(TestAiProviderConnectionCommand::class)->implementsInterface(NonTransactionalCommand::class));
    }

    private function handler(FakeLlmClient $client): TestAiProviderConnectionCommandHandler
    {
        return new TestAiProviderConnectionCommandHandler(
            $this->repository,
            new FakeLlmClientFactory($client)
        );
    }

    private function command(
        ?ApiToken $apiToken,
        ?int $id = null,
        AiProviderTypeEnum $type = AiProviderTypeEnum::Anthropic,
        string $endpointUrl = 'https://api.anthropic.com',
        ReasoningModeEnum $modelReasoning = ReasoningModeEnum::Minimal,
    ): TestAiProviderConnectionCommand {
        return new TestAiProviderConnectionCommand(
            type: $type,
            model: new ModelName('claude-sonnet-4-6'),
            endpointUrl: new EndpointUrl($endpointUrl),
            apiToken: $apiToken,
            id: $id !== null ? new AiProviderId($id) : null,
            modelReasoning: $modelReasoning,
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
