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
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\ReadModel\ActivityLogEntry;
use App\ActivityLogging\Domain\ReadModel\ActivityLogPage;
use App\ActivityLogging\Domain\ReadModel\ResolvedTarget;
use App\ActivityLogging\Domain\Repository\ActivityLogReadRepository;
use App\ActivityLogging\Domain\Repository\ActorNameRepository;
use App\ActivityLogging\Domain\Repository\Criteria\ActivityLogCriteria;
use App\ActivityLogging\Domain\Repository\ServiceParentRepository;
use App\ActivityLogging\Domain\Service\ServiceTargetResolver;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogActorOutput;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogResource;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogTargetOutput;
use App\ActivityLogging\Infrastructure\ApiPlatform\Resource\ActivityLogTargetParentOutput;
use App\Shared\Infrastructure\ApiPlatform\State\FilterAwareProviderTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<ActivityLogResource>
 */
final readonly class ListActivityLogsProvider implements ProviderInterface
{
    use FilterAwareProviderTrait;

    public function __construct(
        private ActivityLogReadRepository $activityLogRepository,
        private ActorNameRepository $actorNameRepository,
        private ServiceParentRepository $serviceParentRepository,
        private ServiceTargetResolver $serviceTargetResolver,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<ActivityLogResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        $criteria = $this->createCriteria($filters);
        if ($this->pagination->isEnabled($operation, $context)) {
            $criteria = $criteria->withPagination(
                $this->pagination->getPage($context),
                $this->pagination->getLimit($operation, $context),
            );
        }

        $result = $this->activityLogRepository->findAll($criteria);
        if (! $result instanceof ActivityLogPage) {
            return $this->createResources(iterator_to_array($result, false));
        }

        return new ActivityLogPartialPaginator(
            $this->createResources($result->entries),
            $result->page,
            $result->itemsPerPage,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function createCriteria(array $filters): ActivityLogCriteria
    {
        $criteria = new ActivityLogCriteria();

        $targetName = $filters['object_name'] ?? null;
        if ($targetName !== null) {
            if (! is_string($targetName)) {
                throw new BadRequestHttpException('The "object_name" filter must be a string.');
            }
            if ($targetName !== '') {
                $criteria = $criteria->withTargetName($targetName);
            }
        }

        foreach ($this->listFilter($filters, 'actor_id') as $actorId) {
            $criteria = $criteria->withActorId((int) $this->handlePositiveIntFilter($actorId, 'actor_id'));
        }

        foreach ($this->listFilter($filters, 'target_type') as $targetType) {
            $criteria = $criteria->withTargetType(
                TargetTypeEnum::tryFrom($targetType)
                    ?? throw new BadRequestHttpException(sprintf('The "target_type" filter must be one of "%s".', implode('", "', ActivityLogResource::TARGET_TYPES)))
            );
        }

        $targetId = $this->handlePositiveIntFilter($filters['target_id'] ?? null, 'target_id');
        if ($targetId !== null) {
            $criteria = $criteria->withTargetId($targetId);
        }

        foreach ($this->listFilter($filters, 'action') as $action) {
            $criteria = $criteria->withAction(
                ActionEnum::tryFrom($action)
                    ?? throw new BadRequestHttpException(sprintf('The "action" filter must be one of "%s".', implode('", "', ActivityLogResource::ACTIONS)))
            );
        }

        $date = $filters['date'] ?? null;
        if ($date !== null) {
            if (! is_array($date)) {
                throw new BadRequestHttpException('The "date" filter must use the "date[from|to]=value" format.');
            }

            $criteria = $criteria->withPerformedBetween(
                $this->parseDate($date['from'] ?? null, 'date[from]'),
                $this->parseDate($date['to'] ?? null, 'date[to]'),
            );
        }

        $sort = $filters['sort'] ?? [];
        if (! is_array($sort) || array_diff(array_keys($sort), ['date']) !== []) {
            throw new BadRequestHttpException('The "sort" parameter only accepts "sort[date]=asc|desc".');
        }
        $dateDirection = $sort['date'] ?? 'desc';
        if (! is_string($dateDirection) || ! in_array(mb_strtolower($dateDirection), ['asc', 'desc'], true)) {
            throw new BadRequestHttpException('The sort direction of "date" must be "asc" or "desc".');
        }

        return $criteria->withOldestFirst(mb_strtolower($dateDirection) === 'asc');
    }

    /**
     * Accepts both "<name>=value" and "<name>[]=value" forms.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<string>
     */
    private function listFilter(array $filters, string $filterName): array
    {
        $values = $filters[$filterName] ?? [];
        $values = is_array($values) ? $values : [$values];

        $list = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                throw new BadRequestHttpException(sprintf('The "%s" filter must be a list of values.', $filterName));
            }
            if ((string) $value !== '') {
                $list[] = (string) $value;
            }
        }

        return $list;
    }

    private function parseDate(mixed $value, string $filterName): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        // ISO 8601 date-time, with optional fraction and either "Z" or an offset
        if (
            ! is_string($value)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value)
        ) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be an ISO 8601 date-time.', $filterName));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be an ISO 8601 date-time.', $filterName));
        }
    }

    /**
     * Resolves the actor names and the service parents of the whole page at once.
     *
     * @param list<ActivityLogEntry> $entries
     *
     * @return list<ActivityLogResource>
     */
    private function createResources(array $entries): array
    {
        $actorIds = [];
        $serviceIds = [];
        foreach ($entries as $entry) {
            if ($entry->actorId !== null) {
                $actorIds[] = $entry->actorId;
            }
            if ($entry->targetType === TargetTypeEnum::Service) {
                $serviceIds[] = $entry->targetId;
            }
        }

        $actorNames = $this->actorNameRepository->findNames(...array_unique($actorIds));
        $serviceParents = $this->serviceParentRepository->findByServiceIds(...array_unique($serviceIds));

        return array_map(
            function (ActivityLogEntry $entry) use ($actorNames, $serviceParents): ActivityLogResource {
                $target = $entry->targetType === TargetTypeEnum::Service
                    ? $this->serviceTargetResolver->resolve($entry->targetName, $serviceParents[$entry->targetId] ?? null)
                    : new ResolvedTarget($entry->targetName, null);

                return new ActivityLogResource(
                    id: $entry->id,
                    date: $entry->performedAt,
                    action: $entry->action->value,
                    target: new ActivityLogTargetOutput(
                        id: $entry->targetId,
                        type: $entry->targetType->value,
                        name: $target->name,
                        parent: $target->parent !== null
                            ? new ActivityLogTargetParentOutput($target->parent->type->value, $target->parent->names)
                            : null,
                    ),
                    actor: $entry->actorId !== null
                        ? new ActivityLogActorOutput($entry->actorId, $actorNames[$entry->actorId] ?? null)
                        : null,
                    hasChanges: $entry->hasChanges,
                );
            },
            $entries,
        );
    }
}
