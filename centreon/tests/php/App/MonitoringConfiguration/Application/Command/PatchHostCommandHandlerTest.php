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

use App\MonitoringConfiguration\Application\Command\PatchHostCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class PatchHostCommandHandlerTest extends TestCase
{
    private const HOST_ID = 5;

    private FakeHostRepository $repository;

    private EventBusSpy $eventBus;

    private PatchHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new PatchHostCommandHandler($this->repository, $this->eventBus);
    }

    public function testItEnablesADisabledHost(): void
    {
        $host = $this->seedHost(activated: false);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            activated: true,
            updatedBy: 1,
        ));

        self::assertTrue($result->activated);
        self::assertTrue($host->activated);
        self::assertSame([['id' => self::HOST_ID, 'activated' => true]], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostEnabled::class, 1));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
    }

    public function testItDisablesAnEnabledHost(): void
    {
        $host = $this->seedHost(activated: true);

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            activated: false,
            updatedBy: 1,
        ));

        self::assertFalse($result->activated);
        self::assertFalse($host->activated);
        self::assertSame([['id' => self::HOST_ID, 'activated' => false]], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDisabled::class, 1));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
    }

    public function testItIsASilentNoOpWhenAlreadyInTheRequestedState(): void
    {
        $this->seedHost(activated: true);

        ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            activated: true,
            updatedBy: 1,
        ));

        self::assertSame([], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
    }

    public function testItFailsWhenTheHostDoesNotExist(): void
    {
        $this->expectException(HostNotFoundException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(404),
                activated: true,
                updatedBy: 1,
            ));
        } finally {
            self::assertSame([], $this->repository->activationUpdates);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostEnabled::class));
        }
    }

    public function testItHidesAHostOutsideTheViewerAclScope(): void
    {
        $this->seedHost(activated: true);
        // The host exists, but the restricted viewer is not allowed to see it.
        $this->repository->accessibleHostIds = [];

        $this->expectException(HostNotFoundException::class);

        try {
            ($this->handler)(new PatchHostCommand(
                id: new HostId(self::HOST_ID),
                activated: false,
                updatedBy: 1,
                viewerId: new UserId(42),
            ));
        } finally {
            self::assertSame([], $this->repository->activationUpdates);
            self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostDisabled::class));
        }
    }

    public function testItDisablesAHostVisibleToARestrictedViewer(): void
    {
        $host = $this->seedHost(activated: true);
        // The restricted viewer is allowed to see this host, so the toggle goes through.
        $this->repository->accessibleHostIds = [self::HOST_ID];

        $result = ($this->handler)(new PatchHostCommand(
            id: new HostId(self::HOST_ID),
            activated: false,
            updatedBy: 1,
            viewerId: new UserId(42),
        ));

        self::assertFalse($result->activated);
        self::assertFalse($host->activated);
        self::assertSame([['id' => self::HOST_ID, 'activated' => false]], $this->repository->activationUpdates);
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDisabled::class, 1));
    }

    private function seedHost(bool $activated): Host
    {
        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        return $this->repository->seed($host, self::HOST_ID);
    }
}
