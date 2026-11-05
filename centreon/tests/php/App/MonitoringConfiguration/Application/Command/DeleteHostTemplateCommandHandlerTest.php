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

use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateCommand;
use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateDeleted;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteHostTemplateCommandHandlerTest extends TestCase
{
    private FakeHostTemplateRepository $repository;

    private FakePollerRepository $pollerRepository;

    private EventBusSpy $eventBus;

    private DeleteHostTemplateCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostTemplateRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->eventBus = new EventBusSpy();

        $this->handler = new DeleteHostTemplateCommandHandler($this->repository, $this->pollerRepository, $this->eventBus);
    }

    public function testItThrowsWhenTheTemplateDoesNotExist(): void
    {
        $this->expectException(HostTemplateNotFoundException::class);

        ($this->handler)(new DeleteHostTemplateCommand(new HostTemplateId(999), TestActingUsers::unrestricted()));
    }

    public function testItRefusesALockedTemplate(): void
    {
        $this->repository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('locked'), isLocked: true);

        try {
            ($this->handler)(new DeleteHostTemplateCommand(new HostTemplateId(7), TestActingUsers::unrestricted()));
            self::fail('A locked template must not be deleted.');
        } catch (HostTemplateLockedException) {
            self::assertSame([], $this->repository->removedIds);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateDeleted::class));
        }
    }

    public function testItDeletesTheTemplateAndFlagsThePollersOfItsInheritingHosts(): void
    {
        $this->repository->hostTemplates[7] = new HostTemplate(new HostTemplateId(7), new HostTemplateName('generic'));
        $this->repository->inheritingHostPollerIds[7] = [3, 5];

        ($this->handler)(new DeleteHostTemplateCommand(new HostTemplateId(7), TestActingUsers::unrestricted()));

        self::assertSame([7], $this->repository->removedIds);
        self::assertSame([3, 5], $this->pollerRepository->flaggedPollerIds);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostTemplateDeleted::class, 1));
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostTemplateVaultPurgeRequested::class, 1));
    }
}
