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

use App\AiConfiguration\Domain\Event\AiProviderDeleted;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class DeleteAiProviderCommandHandler
{
    public function __construct(
        private AiProviderRepository $repository,
        private AiProviderTokenStorage $tokenStorage,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DeleteAiProviderCommand $command): void
    {
        $aiProvider = $this->repository->getById($command->id);

        // deleting the activated provider is allowed: it turns AI features off
        $this->repository->delete($aiProvider);
        $this->tokenStorage->remove($aiProvider->apiToken);

        $this->eventBus->fire(new AiProviderDeleted($aiProvider, $command->deletedBy));
    }
}
