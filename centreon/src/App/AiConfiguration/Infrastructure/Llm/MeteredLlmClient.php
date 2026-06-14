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
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;
use App\AiConfiguration\Domain\Usage\AiProviderUsageRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;
use App\AiConfiguration\Domain\Usage\UsageLogEntry;
use App\Security\Infrastructure\Security\CredentialUser;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Wraps a client to enforce the provider spend limit before each request and
 * record token usage and cost after each successful one. The triggering user
 * is read from the security context; the feature comes from the request.
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
        $response = $this->inner->complete($request);
        $this->record($request, $response);

        return $response;
    }

    public function stream(LlmRequest $request): \Generator
    {
        $this->assertWithinBudget();
        $response = yield from $this->inner->stream($request);
        $this->record($request, $response);

        return $response;
    }

    public function listModels(): array
    {
        // listing models is a configuration-time lookup: not billed or metered
        return $this->inner->listModels();
    }

    private function assertWithinBudget(): void
    {
        if ($this->budgetChecker->isExhausted($this->provider)) {
            throw LlmConfigurationException::budgetExceeded();
        }
    }

    private function record(LlmRequest $request, LlmResponse $response): void
    {
        $cost = null;
        $currency = null;
        if ($this->provider->pricing !== null && $this->provider->currency !== null) {
            $cost = $this->provider->pricing->costOf($request->tier, $response->usage);
            $currency = $this->provider->currency->value;
        }

        $this->usageRepository->add(new UsageLogEntry(
            providerId: $this->provider->id()->value,
            providerName: $this->provider->name->value,
            feature: $request->feature,
            contactId: $this->currentUserId(),
            model: $response->model,
            tier: $request->tier,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            cost: $cost,
            currency: $currency,
            requestedAt: $this->clock->now()->getTimestamp(),
        ));
    }

    private function currentUserId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof CredentialUser ? $user->credential->userId->value : null;
    }
}
