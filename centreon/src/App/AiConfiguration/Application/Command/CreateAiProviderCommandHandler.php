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
use App\AiConfiguration\Domain\Event\AiProviderCreated;
use App\AiConfiguration\Domain\Event\AiProviderUpdated;
use App\AiConfiguration\Domain\Exception\AiProviderActivationConflictException;
use App\AiConfiguration\Domain\Exception\AiProviderAlreadyExistsException;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Exception\VaultWriteFailedException;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreateAiProviderCommandHandler
{
    public function __construct(
        private AiProviderRepository $repository,
        private AiProviderTokenStorage $tokenStorage,
        private EventBus $eventBus,
    ) {
    }

    /**
     * @throws AiProviderAlreadyExistsException
     * @throws AiProviderActivationConflictException when another provider was enabled concurrently
     * @throws InvalidAiProviderConfigurationException when the endpoint does not fit the type
     * @throws VaultWriteFailedException
     */
    public function __invoke(CreateAiProviderCommand $command): AiProvider
    {
        if ($this->repository->findOneByName($command->name) instanceof AiProvider) {
            throw new AiProviderAlreadyExistsException(
                ['name' => $command->name->value],
                sprintf("An AI provider configuration with name '%s' already exists.", $command->name->value),
                409
            );
        }

        // The first configuration is always activated.
        $isActivated = $command->isActivated || $this->repository->countAll() === 0;

        // written before the configuration is saved, so that an unreachable vault saves nothing
        try {
            $apiToken = $this->tokenStorage->persist($command->apiToken);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forCreation($exception);
        }

        try {
            $aiProvider = new AiProvider(
                id: null,
                name: $command->name,
                type: $command->type,
                endpointUrl: $command->endpointUrl,
                apiToken: $apiToken,
                defaultModel: $command->defaultModel,
                fastModel: $command->fastModel,
                description: $command->description,
                isActivated: $isActivated,
                billing: $command->billing,
            );

            // at most one provider is activated: enabling this one disables the current one
            $currentlyActivated = $isActivated ? $this->repository->findActivated() : null;
            if ($currentlyActivated instanceof AiProvider) {
                $currentlyActivated->deactivate();
                $this->repository->update($currentlyActivated);
            }

            $this->repository->add($aiProvider);
        } catch (\Throwable $exception) {
            // nothing references the written token
            $this->tokenStorage->discard($apiToken, null);

            throw $exception;
        }

        $this->eventBus->fire(new AiProviderCreated($aiProvider, $command->creatorId));
        if ($currentlyActivated instanceof AiProvider) {
            $this->eventBus->fire(new AiProviderUpdated($currentlyActivated, $command->creatorId));
        }

        return $aiProvider;
    }
}
