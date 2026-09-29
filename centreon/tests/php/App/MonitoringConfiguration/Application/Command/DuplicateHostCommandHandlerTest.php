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

use App\MonitoringConfiguration\Application\Command\DuplicateHostCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostDuplicated;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\Security\Infrastructure\Double\FakeAccessGroupRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class DuplicateHostCommandHandlerTest extends TestCase
{
    private FakeHostRepository $repository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private FakeAccessGroupRepository $accessGroupRepository;

    private EventBusSpy $eventBus;

    private DuplicateHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeHostRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->accessGroupRepository = new FakeAccessGroupRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DuplicateHostCommandHandler(
            $this->repository,
            $this->resourceAccessRepository,
            $this->accessGroupRepository,
            $this->eventBus,
        );
    }

    public function testDuplicatesHostWithFirstFreeSuffixAndCopiesEveryRelation(): void
    {
        $source = $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy, 'the copy is persisted under the first free "_1" suffix');
        self::assertNotSame($source->id()->value, $copy->id()->value);
        self::assertSame($source->alias?->value, $copy->alias?->value);
        self::assertSame($source->address->value, $copy->address->value);
        self::assertSame($source->activated, $copy->activated);
        self::assertSame($source->pollerId->value, $copy->pollerId->value);
        self::assertSame($this->idValues($source->templateIds), $this->idValues($copy->templateIds));
        self::assertSame($this->idValues($source->hostGroupIds), $this->idValues($copy->hostGroupIds));
        self::assertSame($this->idValues($source->categoryIds), $this->idValues($copy->categoryIds));
    }

    public function testSkipsAlreadyTakenSuffixes(): void
    {
        $this->storeSourceHost(1, 'web');
        $this->storeSourceHost(2, 'web_1');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));

        self::assertNotNull($this->findCopyByName('web_2'), 'the next free suffix is used instead');
        self::assertCount(3, $this->repository->hosts, 'no extra copy is created for the taken suffix');
    }

    public function testThrowsNotFoundWhenSourceDoesNotExist(): void
    {
        $this->expectException(HostNotFoundException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(999), duplicatedBy: 42, viewerId: null));
    }

    public function testThrowsConflictWhenEveryCandidateNameIsTaken(): void
    {
        $this->storeSourceHost(1, 'web');
        $this->repository->forceNameUsed = true;

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));
    }

    public function testCopiesTheSourceAclScopeOntoTheCopy(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));

        $copy = $this->findCopyByName('web_1');
        self::assertNotNull($copy);
        self::assertSame(
            [['sourceHostId' => 1, 'newHostId' => $copy->id()->value]],
            $this->resourceAccessRepository->duplicatedHostAccess,
        );
    }

    public function testFiresHostDuplicatedOnceForLogAndPollerFlag(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDuplicated::class, 1));
        $event = $this->eventBus->getDispatchedEvents(HostDuplicated::class)[0];
        self::assertSame(42, $event->creatorId);
    }

    public function testAdminFlagsEveryResourceForReload(): void
    {
        $this->storeSourceHost(1, 'web');

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));

        self::assertTrue($this->resourceAccessRepository->allResourcesFlaggedAsChanged);
        self::assertSame([], $this->accessGroupRepository->flaggedGroupIds);
    }

    public function testNonAdminFlagsOnlyOwnAccessGroupsForReload(): void
    {
        $this->storeSourceHost(1, 'web');
        $this->accessGroupRepository->groupIdsByUserId[7] = [10, 20];

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 7, viewerId: new UserId(7)));

        self::assertFalse($this->resourceAccessRepository->allResourcesFlaggedAsChanged);
        self::assertSame([10, 20], $this->accessGroupRepository->flaggedGroupIds);
    }

    private function storeSourceHost(int $id, string $name): Host
    {
        $host = $this->buildHost($id, $name);
        $this->repository->hosts[$id] = $host;

        return $host;
    }

    private function buildHost(int $id, string $name): Host
    {
        return new Host(
            id: new HostId($id),
            name: new HostName($name),
            alias: new HostAlias('alias-' . $name),
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([new HostTemplateId(5)], HostTemplateId::class),
            hostGroupIds: new Collection([new HostGroupId(3)], HostGroupId::class),
            categoryIds: new Collection([new HostCategoryId(8)], HostCategoryId::class),
        );
    }

    private function findCopyByName(string $name): ?Host
    {
        foreach ($this->repository->hosts as $host) {
            if ($host->name->value === $name) {
                return $host;
            }
        }

        return null;
    }

    /**
     * @param Collection<HostTemplateId>|Collection<HostGroupId>|Collection<HostCategoryId> $collection
     *
     * @return list<int>
     */
    private function idValues(Collection $collection): array
    {
        return array_values(array_map(
            static fn (HostTemplateId|HostGroupId|HostCategoryId $id): int => $id->value,
            $collection->toArray(),
        ));
    }
}
