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

namespace App\ActivityLogging\Domain\Event;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Factory\ActivityLogFactoryInterface;
use App\ActivityLogging\Domain\Repository\ActivityLogRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Event\AggregateCreated;
use App\Shared\Domain\Event\AggregateDeleted;
use App\Shared\Domain\Event\AggregateUpdated;
use App\Shared\Domain\Event\AsEventHandler;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

#[AsEventHandler]
final readonly class LogActivityEventHandler
{
    public function __construct(
        private ActivityLogRepository $repository,
        #[AutowireLocator('activity_logging.activity_log_factory')]
        private ContainerInterface $activityLogFactories,
    ) {
    }

    public function __invoke(AggregateCreated|AggregateUpdated|AggregateDeleted $event): void
    {
        if (! $this->activityLogFactories->has($event->aggregate::class)) {
            throw new \LogicException(\sprintf('There is no "%s" for "%s", did you add a service with "activity_logging.activity_log_factory" tag?', ActivityLogFactoryInterface::class, $event->aggregate::class));
        }

        /** @var ActivityLogFactoryInterface<AggregateRoot<AggregateRootId>> $factory */
        $factory = $this->activityLogFactories->get($event->aggregate::class);

        $action = match (true) {
            $event instanceof AggregateCreated => ActionEnum::Add,
            $event instanceof AggregateUpdated => ActionEnum::Update,
            $event instanceof AggregateDeleted => ActionEnum::Delete,
        };

        $activityLog = $factory->create(
            action: $action,
            aggregate: $event->aggregate,
            firedBy: new Actor(id: new ActorId($event->creatorId)),
            firedAt: $event->firedAt(),
        );

        $this->repository->add($activityLog);
    }
}
