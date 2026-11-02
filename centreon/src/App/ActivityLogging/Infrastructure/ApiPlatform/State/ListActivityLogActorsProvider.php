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

namespace App\ActivityLogging\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ActivityLogging\Domain\Repository\ActivityLogReadRepository;
use App\ActivityLogging\Domain\Repository\ActorNameRepository;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogActorResource;

/**
 * @implements ProviderInterface<ActivityLogActorResource>
 */
final readonly class ListActivityLogActorsProvider implements ProviderInterface
{
    public function __construct(
        private ActivityLogReadRepository $activityLogRepository,
        private ActorNameRepository $actorNameRepository,
    ) {
    }

    /**
     * @return list<ActivityLogActorResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // contacts which no longer exist are left out by the name lookup
        $names = $this->actorNameRepository->findNames(...$this->activityLogRepository->findActorIds());
        natcasesort($names);

        $actors = [];
        foreach ($names as $id => $name) {
            $actors[] = new ActivityLogActorResource($id, $name);
        }

        return $actors;
    }
}
