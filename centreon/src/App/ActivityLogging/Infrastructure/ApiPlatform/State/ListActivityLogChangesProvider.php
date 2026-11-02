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
use App\ActivityLogging\Domain\ReadModel\ActivityLogEntry;
use App\ActivityLogging\Domain\ReadModel\FieldChange;
use App\ActivityLogging\Domain\Repository\ActivityLogReadRepository;
use App\ActivityLogging\Domain\Service\ChangeHistoryBuilder;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogChangeResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ActivityLogChangeResource>
 */
final readonly class ListActivityLogChangesProvider implements ProviderInterface
{
    public function __construct(
        private ActivityLogReadRepository $activityLogRepository,
        private ChangeHistoryBuilder $changeHistoryBuilder,
    ) {
    }

    /**
     * @return list<ActivityLogChangeResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $id = $uriVariables['id'] ?? null;
        $entry = is_numeric($id) ? $this->activityLogRepository->findEntry((int) $id) : null;
        if (! $entry instanceof ActivityLogEntry) {
            throw new NotFoundHttpException('Activity log not found');
        }

        $changes = $this->changeHistoryBuilder->build(
            $entry->id,
            $this->activityLogRepository->findTargetHistoryUntil($entry),
        );

        return array_map(
            static fn (FieldChange $change): ActivityLogChangeResource => new ActivityLogChangeResource(
                field: $change->field,
                before: $change->before,
                after: $change->after,
            ),
            $changes,
        );
    }
}
