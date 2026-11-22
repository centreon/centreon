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
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Domain\Llm\LlmUsage;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Infrastructure\Llm\MeteredLlmClient;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeAiProviderUsageRepository;
use Tests\App\AiConfiguration\Infrastructure\Double\FakeLlmClient;

final class MeteredLlmClientTest extends TestCase
{
    private const NOW = '2026-03-15 12:00:00';

    public function testRecordsUsageWithCostAfterASuccessfulCall(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(pricing: new Pricing(new TierPricing(3.0, 15.0)), currency: new Currency('USD')),
            $usageRepository,
            new FakeLlmClient(new LlmResponse('hi', 'claude-sonnet-4-6', new LlmUsage(1_000_000, 1_000_000))),
        );

        $client->complete($this->request());

        $entry = $usageRepository->lastAdded;
        self::assertNotNull($entry);
        self::assertSame(AiFeatureEnum::ResourceExplanation, $entry->feature);
        self::assertSame('claude-sonnet-4-6', $entry->model);
        self::assertSame(1_000_000, $entry->inputTokens);
        self::assertSame('USD', $entry->currency);
        self::assertEqualsWithDelta(18.0, $entry->cost, 0.0001);
        self::assertNull($entry->contactId);
        self::assertGreaterThanOrEqual(0, $entry->durationMs);
    }

    public function testRecordsWithoutCostWhenNoPricing(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered($this->provider(), $usageRepository, new FakeLlmClient());

        $client->complete($this->request());

        self::assertNull($usageRepository->lastAdded?->cost);
        self::assertNull($usageRepository->lastAdded?->currency);
    }

    public function testRefusesAndDoesNotRecordWhenBudgetExhausted(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository([
            FakeAiProviderUsageRepository::spend(1, 'USD', 50.0, (new MockClock(self::NOW))->now()->getTimestamp()),
        ]);
        $inner = new FakeLlmClient();
        $client = $this->metered(
            $this->provider(
                pricing: new Pricing(new TierPricing(3.0, 15.0)),
                currency: new Currency('USD'),
                limit: new SpendLimit(daily: 10.0),
            ),
            $usageRepository,
            $inner,
        );

        try {
            $client->complete($this->request());
            self::fail('Expected a budget exception');
        } catch (LlmConfigurationException) {
            // expected
        }

        self::assertNull($inner->lastRequest, 'the inner client must not be called when over budget');
        self::assertSame([], $usageRepository->added);
    }

    public function testEmptyAnswerFailsAfterTheUsageIsRecorded(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(),
            $usageRepository,
            new FakeLlmClient(new LlmResponse('', 'gemini-4-hydrogen', new LlmUsage(37, 3))),
        );

        try {
            $client->complete($this->request());
            self::fail('Expected an empty answer to fail');
        } catch (LlmRequestFailedException $exception) {
            self::assertStringContainsString('empty answer', $exception->getMessage());
        }

        self::assertNotNull($usageRepository->lastAdded, 'the tokens spent must still be recorded');
        self::assertSame(3, $usageRepository->lastAdded->outputTokens);
    }

    public function testTruncatedEmptyAnswerNamesTheTokenBudget(): void
    {
        $client = $this->metered(
            $this->provider(),
            new FakeAiProviderUsageRepository(),
            new FakeLlmClient(new LlmResponse('', 'gemini-4-hydrogen', new LlmUsage(37, 1024), truncated: true)),
        );

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('whole budget of 1024 tokens');

        $client->complete($this->request());
    }

    public function testConnectionTestAcceptsAnEmptyAnswer(): void
    {
        $client = $this->metered(
            $this->provider(),
            new FakeAiProviderUsageRepository(),
            new FakeLlmClient(new LlmResponse('', 'gemini-4-hydrogen', new LlmUsage(7, 16), truncated: true)),
        );

        $response = $client->complete(LlmRequest::connectionTest(null));

        self::assertSame('gemini-4-hydrogen', $response->model);
    }

    public function testRecordsTheLoggedInUser(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(),
            $usageRepository,
            new FakeLlmClient(),
            $this->securityWithUser(42),
        );

        $client->complete($this->request());

        self::assertSame(42, $usageRepository->lastAdded?->contactId);
    }

    public function testAFailedCallRecordsNothing(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(),
            $usageRepository,
            new FakeLlmClient(exception: LlmRequestFailedException::fromTransportError('Connection refused')),
        );

        try {
            $client->complete($this->request());
            self::fail('Expected the inner failure to propagate');
        } catch (LlmRequestFailedException) {
            // expected
        }

        self::assertSame([], $usageRepository->added);
    }

    public function testStreamRecordsUsageOnceTheAnswerIsComplete(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository();
        $client = $this->metered(
            $this->provider(pricing: new Pricing(new TierPricing(3.0, 15.0)), currency: new Currency('USD')),
            $usageRepository,
            new FakeLlmClient(new LlmResponse('pong', 'claude-sonnet-4-6', new LlmUsage(1_000_000, 1_000_000))),
            $this->securityWithUser(42),
        );

        $generator = $client->stream($this->request());

        self::assertSame(['pong'], iterator_to_array($generator, false));
        self::assertSame('pong', $generator->getReturn()->content);
        $entry = $usageRepository->lastAdded;
        self::assertNotNull($entry);
        self::assertCount(1, $usageRepository->added);
        self::assertSame(42, $entry->contactId);
        self::assertSame('USD', $entry->currency);
        self::assertEqualsWithDelta(18.0, $entry->cost, 0.0001);
    }

    public function testStreamRefusesAndDoesNotRecordWhenBudgetExhausted(): void
    {
        $usageRepository = new FakeAiProviderUsageRepository([
            FakeAiProviderUsageRepository::spend(1, 'USD', 50.0, (new MockClock(self::NOW))->now()->getTimestamp()),
        ]);
        $inner = new FakeLlmClient();
        $client = $this->metered(
            $this->provider(
                pricing: new Pricing(new TierPricing(3.0, 15.0)),
                currency: new Currency('USD'),
                limit: new SpendLimit(monthly: 10.0),
            ),
            $usageRepository,
            $inner,
        );

        try {
            iterator_to_array($client->stream($this->request()), false);
            self::fail('Expected a budget exception');
        } catch (LlmConfigurationException) {
            // expected
        }

        self::assertNull($inner->lastRequest, 'the inner client must not be called when over budget');
        self::assertSame([], $usageRepository->added);
    }

    private function metered(
        AiProvider $provider,
        FakeAiProviderUsageRepository $usageRepository,
        FakeLlmClient $inner,
        ?Security $security = null,
    ): MeteredLlmClient {
        return new MeteredLlmClient(
            $inner,
            $provider,
            $usageRepository,
            new BudgetStatusChecker($usageRepository, new MockClock(self::NOW)),
            $security ?? $this->securityWithoutUser(),
            new MockClock(self::NOW),
        );
    }

    private function request(): LlmRequest
    {
        return new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'hi')],
            feature: AiFeatureEnum::ResourceExplanation,
        );
    }

    private function provider(
        ?Pricing $pricing = null,
        ?Currency $currency = null,
        ?SpendLimit $limit = null,
    ): AiProvider {
        return new AiProvider(
            id: new AiProviderId(1),
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new StoredApiToken('a-token'),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
            billing: new Billing($currency, $pricing, $limit),
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

    /**
     * A real Security whose token storage holds the token of a logged-in user.
     */
    private function securityWithUser(int $userId): Security
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(
            new CredentialUser(new Credential(
                identifier: new CredentialIdentifier('operator'),
                userId: new UserId($userId),
                active: true,
            )),
            'main',
        ));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        return new Security($container);
    }
}
