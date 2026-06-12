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

namespace App\AiConfiguration\Application\Command;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Event\AiProviderUpdated;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use App\AiConfiguration\Domain\Exception\AiProviderCanNotBeUpdatedException;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class UpdateAiProviderCommandHandler
{
    public function __construct(
        private AiProviderRepository $repository,
        private AiProviderTokenStorage $tokenStorage,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(UpdateAiProviderCommand $command): AiProvider
    {
        $aiProvider = $this->repository->getById($command->id);

        $sameName = $this->repository->findOneByName($command->name);
        if ($sameName instanceof AiProvider && $sameName->id()->value !== $aiProvider->id()->value) {
            throw new AiProviderAlreadyExistsException(
                ['name' => $command->name->value],
                sprintf("An AI provider configuration with name '%s' already exists.", $command->name->value),
                409
            );
        }

        if ($aiProvider->isDefault && ! $command->isDefault) {
            throw new AiProviderCanNotBeUpdatedException(
                ['id' => $aiProvider->id()->value],
                'The default AI provider configuration can not be unset. Set another configuration as default instead.'
            );
        }

        $aiProvider->rename($command->name);
        $aiProvider->updateEndpointUrl($command->endpointUrl);
        $aiProvider->updateDefaultModel($command->defaultModel);
        $aiProvider->updateDescription($command->description);

        if ($command->apiToken instanceof ApiToken) {
            $aiProvider->updateApiToken(
                $this->tokenStorage->persist($command->apiToken, $aiProvider->apiToken)
            );
        }

        $toUpdate = [$aiProvider];
        if ($command->isDefault && ! $aiProvider->isDefault) {
            $currentDefault = $this->repository->findDefault();
            if ($currentDefault instanceof AiProvider) {
                $currentDefault->unmarkAsDefault();
                $toUpdate[] = $currentDefault;
            }
            $aiProvider->markAsDefault();
        }

        $this->repository->update(...$toUpdate);

        $this->eventBus->fire(new AiProviderUpdated($aiProvider, $command->updatedBy));

        return $aiProvider;
    }
}
