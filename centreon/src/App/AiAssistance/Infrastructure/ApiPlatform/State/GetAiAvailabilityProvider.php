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

namespace App\AiAssistance\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\AiAssistance\Infrastructure\ApiPlatform\Resource\AiAvailabilityResource;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Usage\BudgetStatusChecker;

/**
 * @implements ProviderInterface<AiAvailabilityResource>
 */
final readonly class GetAiAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private AiProviderRepository $repository,
        private BudgetStatusChecker $budgetChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AiAvailabilityResource
    {
        $aiProvider = $this->repository->findActivated();

        if (! $aiProvider instanceof AiProvider) {
            return new AiAvailabilityResource(isAvailable: false, reason: 'no_provider');
        }

        if ($this->budgetChecker->isExhausted($aiProvider)) {
            return new AiAvailabilityResource(isAvailable: false, reason: 'budget_exhausted');
        }

        return new AiAvailabilityResource(isAvailable: true);
    }
}
