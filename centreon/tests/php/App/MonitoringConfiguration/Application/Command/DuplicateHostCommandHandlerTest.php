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
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
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
        self::assertSame($this->idValues($source->parentHostIds), $this->idValues($copy->parentHostIds));
        self::assertSame($this->idValues($source->childHostIds), $this->idValues($copy->childHostIds));
        // The remaining fields are forwarded straight from the source aggregate; identity assertions
        // catch a field accidentally dropped or crossed in the copy constructor (it would become a
        // fresh default instead of the source's value).
        self::assertSame($source->snmpVersion, $copy->snmpVersion);
        self::assertSame($source->snmpCommunity, $copy->snmpCommunity);
        self::assertSame($source->timezoneId, $copy->timezoneId);
        self::assertSame($source->severityId, $copy->severityId);
        self::assertSame($source->extendedInformations, $copy->extendedInformations);
        self::assertSame($source->schedulingOptions, $copy->schedulingOptions);
        self::assertSame($source->dataProcessing, $copy->dataProcessing);
        self::assertSame($source->checkOptions, $copy->checkOptions);
    }

    public function testThrowsConflictWhenTheSuffixWouldExceedTheNameLengthLimit(): void
    {
        // A source name already at the maximum length leaves no room for the "_<n>" suffix, so no
        // candidate is valid — this must surface as a 409, not an unmapped 500 from HostName.
        $this->storeSourceHost(1, str_repeat('a', HostName::MAX_LENGTH));

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)(new DuplicateHostCommand(new HostId(1), duplicatedBy: 42, viewerId: null));
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
            parentHostIds: new Collection([new HostId(10)], HostId::class),
            childHostIds: new Collection([new HostId(11)], HostId::class),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('public'),
            timezoneId: new TimezoneId(3),
            severityId: new HostSeverityId(4),
            extendedInformations: new ExtendedInformations(note: 'a note'),
            schedulingOptions: new SchedulingOptions(),
            dataProcessing: new DataProcessing(),
            checkOptions: new CheckOptions(null),
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
     * @param Collection<HostTemplateId>|Collection<HostGroupId>|Collection<HostCategoryId>|Collection<HostId> $collection
     *
     * @return list<int>
     */
    private function idValues(Collection $collection): array
    {
        return array_values(array_map(
            static fn (HostTemplateId|HostGroupId|HostCategoryId|HostId $id): int => $id->value,
            $collection->toArray(),
        ));
    }
}
