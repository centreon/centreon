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
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Repository\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;
use App\Security\Infrastructure\Security\CredentialUser;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Wraps a client to enforce the provider spend limit before each request and
 * record token usage, cost and duration after each successful one. The
 * triggering user is read from the security context; the feature comes from
 * the request. A call that requires an answer and gets an empty one fails,
 * after being recorded.
 */
final readonly class MeteredLlmClient implements LlmClientInterface
{
    public function __construct(
        public LlmClientInterface $inner,
        private AiProvider $provider,
        private AiProviderUsageRepository $usageRepository,
        private BudgetStatusChecker $budgetChecker,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $this->assertWithinBudget();
        $startedAt = hrtime(true);
        $response = $this->inner->complete($request);
        $this->record($request, $response, $this->millisecondsSince($startedAt));
        $this->assertAnswered($request, $response);

        return $response;
    }

    public function stream(LlmRequest $request): \Generator
    {
        $this->assertWithinBudget();
        // until the last chunk: the time the user waits for the whole answer
        $startedAt = hrtime(true);
        $response = yield from $this->inner->stream($request);
        $this->record($request, $response, $this->millisecondsSince($startedAt));
        $this->assertAnswered($request, $response);

        return $response;
    }

    public function listModels(): array
    {
        // listing models is a configuration-time lookup: not billed or metered
        return $this->inner->listModels();
    }

    /**
     * @throws LlmConfigurationException when the provider has reached its spend limit
     */
    private function assertWithinBudget(): void
    {
        if ($this->budgetChecker->isExhausted($this->provider)) {
            throw LlmConfigurationException::budgetExceeded();
        }
    }

    /**
     * An empty answer is still billed: it fails only after the usage is recorded.
     *
     * @throws LlmRequestFailedException when the request requires an answer and got none
     */
    private function assertAnswered(LlmRequest $request, LlmResponse $response): void
    {
        if ($request->requiresAnswer && trim($response->content) === '') {
            throw LlmRequestFailedException::fromEmptyAnswer($response->truncated, $request->maxTokens);
        }
    }

    /**
     * @param int $startedAt a monotonic timestamp from hrtime(true), in nanoseconds
     */
    private function millisecondsSince(int $startedAt): int
    {
        return intdiv(hrtime(true) - $startedAt, 1_000_000);
    }

    private function record(LlmRequest $request, LlmResponse $response, int $durationMs): void
    {
        $cost = $this->provider->billing()->costOf($request->tier, $response->usage);
        // a cost always comes with its currency: the billing requires one along with prices
        $currency = $cost !== null ? $this->provider->billing()->currency?->value : null;

        $this->usageRepository->add(new UsageLogEntry(
            providerId: $this->provider->id()->value,
            providerName: $this->provider->name()->value,
            feature: $request->feature,
            contactId: $this->currentUserId(),
            model: $response->model,
            tier: $request->tier,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            cost: $cost,
            currency: $currency,
            requestedAt: $this->clock->now()->getTimestamp(),
            durationMs: $durationMs,
        ));
    }

    private function currentUserId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof CredentialUser ? $user->credential->userId->value : null;
    }
}
