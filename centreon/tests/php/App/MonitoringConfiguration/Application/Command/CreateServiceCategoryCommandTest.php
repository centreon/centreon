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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\CreateServiceCategoryCommand;
use App\MonitoringConfiguration\Application\Command\CreateServiceCategoryCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Event\ServiceCategoryCreated;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryAlreadyExistsException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateServiceCategoryCommandTest extends TestCase
{
    public function testCreateServiceCategory(): void
    {
        $repository = new FakeServiceCategoryRepository();
        $eventBus = new EventBusSpy();

        $handler = new CreateServiceCategoryCommandHandler($repository, $eventBus);

        $handler(new CreateServiceCategoryCommand(
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
            creatorId: 1,
        ));

        self::assertNotNull($repository->findOneByName(new ServiceCategoryName('NAME')));
    }

    public function testCannotCreateSameServiceCategory(): void
    {
        $repository = new FakeServiceCategoryRepository();
        $eventBus = new EventBusSpy();

        $handler = new CreateServiceCategoryCommandHandler($repository, $eventBus);

        $handler(new CreateServiceCategoryCommand(
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
            creatorId: 1,
        ));

        $this->expectException(ServiceCategoryAlreadyExistsException::class);

        $handler(new CreateServiceCategoryCommand(
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
            creatorId: 1,
        ));
    }

    public function testDispatchCreatedEvent(): void
    {
        $repository = new FakeServiceCategoryRepository();
        $eventBus = new EventBusSpy();

        $handler = new CreateServiceCategoryCommandHandler($repository, $eventBus);

        $handler(new CreateServiceCategoryCommand(
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
            creatorId: 1,
        ));

        self::assertTrue($eventBus->shouldHaveDispatched(ServiceCategoryCreated::class));
    }
}
