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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\Security\Infrastructure\Double\FakeAccessGroupRepository;

final class DbalHostRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private Connection $realTimeConnection;

    private FakeAccessGroupRepository $accessGroupRepository;

    private DbalHostRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');
        $this->realTimeConnection = $realTimeConnection;

        $this->accessGroupRepository = new FakeAccessGroupRepository();

        $this->repository = new DbalHostRepository(
            $this->connection,
            $this->realTimeConnection,
            new DbalHostTransformer(),
            $this->accessGroupRepository,
        );
    }

    public function testItMapsAHostWithItsPollerAndTemplates(): void
    {
        $pollerId = $this->createPoller('Central');
        $templateOneId = $this->createHostTemplate('generic-active-host');
        $templateTwoId = $this->createHostTemplate('generic-passive-host');
        $hostId = $this->createHost('server-01', $pollerId, alias: 'srv01', address: '10.0.0.1');
        $this->linkHostToTemplate($hostId, $templateOneId);
        $this->linkHostToTemplate($hostId, $templateTwoId);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertCount(1, $hosts);
        $host = $hosts[0];
        self::assertSame($hostId, $host->id()->value);
        self::assertSame('server-01', $host->name->value);
        self::assertSame('srv01', $host->alias?->value);
        self::assertSame('10.0.0.1', $host->address->value);
        self::assertTrue($host->activated);
        self::assertSame($pollerId, $host->pollerId->value);
        self::assertEqualsCanonicalizing(
            [$templateOneId, $templateTwoId],
            array_map(static fn (HostTemplateId $id): int => $id->value, iterator_to_array($host->templateIds)),
        );
    }

    public function testItDoesNotDuplicateAHostWithMultipleTemplates(): void
    {
        // The join used to gather template_ids is one-to-many; without the GROUP BY the
        // host would appear once per linked template instead of once overall.
        $pollerId = $this->createPoller('Central');
        $templateOneId = $this->createHostTemplate('template-a');
        $templateTwoId = $this->createHostTemplate('template-b');
        $hostId = $this->createHost('server-02', $pollerId);
        $this->linkHostToTemplate($hostId, $templateOneId);
        $this->linkHostToTemplate($hostId, $templateTwoId);

        self::assertCount(1, iterator_to_array($this->repository->findAll()));
    }

    public function testItTreatsAnEmptyAliasAsNull(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('server-03', $pollerId, alias: '');

        $host = iterator_to_array($this->repository->findAll())[0];

        self::assertNull($host->alias);
    }

    public function testItExcludesHostTemplatesFromTheListing(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHostTemplate('a-template');
        $this->createHost('a-real-host', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll());

        self::assertCount(1, $hosts);
        self::assertSame('a-real-host', $hosts[0]->name->value);
    }

    public function testItFiltersByNameUsingLike(): void
    {
        $pollerId = $this->createPoller('Central');
        $matchingId = $this->createHost('web-frontend', $pollerId);
        $this->createHost('database-backend', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withName('front')));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByTemplateId(): void
    {
        $pollerId = $this->createPoller('Central');
        $templateId = $this->createHostTemplate('linux-template');
        $matchingId = $this->createHost('host-with-template', $pollerId);
        $this->linkHostToTemplate($matchingId, $templateId);
        $this->createHost('host-without-template', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withTemplateId($templateId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByGroupId(): void
    {
        $pollerId = $this->createPoller('Central');
        $groupId = $this->createHostGroup('Linux-Servers');
        $matchingId = $this->createHost('grouped-host', $pollerId);
        $this->linkHostToGroup($matchingId, $groupId);
        $this->createHost('ungrouped-host', $pollerId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withGroupId($groupId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByPollerId(): void
    {
        $pollerOneId = $this->createPoller('Central');
        $pollerTwoId = $this->createPoller('Remote-Poller');
        $matchingId = $this->createHost('on-poller-one', $pollerOneId);
        $this->createHost('on-poller-two', $pollerTwoId);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withPollerId($pollerOneId)));

        self::assertCount(1, $hosts);
        self::assertSame($matchingId, $hosts[0]->id()->value);
    }

    public function testItFiltersByActivatedStatus(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('active-host', $pollerId, activated: true);
        $inactiveId = $this->createHost('inactive-host', $pollerId, activated: false);

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withActivated(false)));

        self::assertCount(1, $hosts);
        self::assertSame($inactiveId, $hosts[0]->id()->value);
    }

    public function testItPaginatesResults(): void
    {
        $pollerId = $this->createPoller('Central');
        for ($hostNumber = 1; $hostNumber <= 3; $hostNumber++) {
            $this->createHost('host-' . $hostNumber, $pollerId);
        }

        $result = $this->repository->findAll((new HostCriteria())->withPagination(1, 2));

        self::assertInstanceOf(InMemoryPaginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertCount(2, iterator_to_array($result));
    }

    public function testViewerWithNoAccessibleGroupSeesNoHosts(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('any-host', $pollerId);

        $viewerId = new UserId(42);
        // No entry in $this->accessGroupRepository->groupIdsByUserId: the viewer belongs to no group.

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withViewerId($viewerId)));

        self::assertCount(0, $hosts);
    }

    public function testViewerSeesOnlyHostsAccessibleThroughItsGroups(): void
    {
        $pollerId = $this->createPoller('Central');
        $accessibleId = $this->createHost('accessible-host', $pollerId);
        $this->createHost('restricted-host', $pollerId);

        $viewerId = new UserId(43);
        $groupId = 501;
        $this->accessGroupRepository->groupIdsByUserId[$viewerId->value] = [$groupId];
        $this->linkHostToAcl($accessibleId, $groupId);
        // The restricted host is deliberately not linked to any centreon_acl row for this group.

        $hosts = iterator_to_array($this->repository->findAll((new HostCriteria())->withViewerId($viewerId)));

        self::assertCount(1, $hosts);
        self::assertSame($accessibleId, $hosts[0]->id()->value);
    }

    public function testNoViewerIdSeesEveryHostRegardlessOfAcl(): void
    {
        // No withViewerId() call: mirrors how the Provider skips ACL scoping entirely for an admin.
        $pollerId = $this->createPoller('Central');
        $this->createHost('unrestricted-host', $pollerId);

        self::assertCount(1, iterator_to_array($this->repository->findAll()));
    }

    public function testAddPersistsTheHostAndItsRelations(): void
    {
        $pollerId = $this->createPoller('Central');
        $groupId = $this->createHostGroup('Linux servers');

        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: new HostAlias('srv01'),
            address: new HostAddress('10.0.0.1'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([new HostGroupId($groupId)], HostGroupId::class),
        );

        $this->repository->add($host);

        self::assertGreaterThan(0, $host->id()->value);

        $hosts = iterator_to_array($this->repository->findAll());
        self::assertCount(1, $hosts);
        $persisted = array_values($hosts)[0];
        self::assertSame('server-01', $persisted->name->value);
        self::assertSame('srv01', $persisted->alias?->value);
        self::assertSame('10.0.0.1', $persisted->address->value);
        self::assertSame($pollerId, $persisted->pollerId->value);
        self::assertSame([$groupId], array_map(static fn (HostGroupId $id): int => $id->value, $persisted->hostGroupIds->toArray()));

        // Every host gets a companion row here, even with no optional field set.
        /** @var int|string $extendedInfoCount */
        $extendedInfoCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM extended_host_information WHERE host_host_id = ?',
            [$host->id()->value],
        );
        self::assertSame(1, (int) $extendedInfoCount);
    }

    public function testExistsByNameFindsAHostByExactName(): void
    {
        $pollerId = $this->createPoller('Central');
        $this->createHost('server-01', $pollerId);

        self::assertTrue($this->repository->existsByName(new HostName('server-01')));
        self::assertFalse($this->repository->existsByName(new HostName('server-02')));
    }

    /**
     * Name uniqueness spans hosts AND host templates in legacy — both share the `host` table
     * and the same uniqueness rule, so a host template with this name must also count as a match.
     */
    public function testExistsByNameFindsAHostTemplateWithTheSameName(): void
    {
        $this->createHostTemplate('shared-name');

        self::assertTrue($this->repository->existsByName(new HostName('shared-name')));
    }

    private function createPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHostTemplate(string $name): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHost(
        string $name,
        int $pollerId,
        ?string $alias = null,
        string $address = '127.0.0.1',
        bool $activated = true,
    ): int {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_alias' => $alias,
            'host_address' => $address,
            'host_activate' => $activated ? '1' : '0',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function linkHostToTemplate(int $hostId, int $templateId): void
    {
        $this->connection->insert('host_template_relation', [
            'host_host_id' => $hostId,
            'host_tpl_id' => $templateId,
        ]);
    }

    private function createHostGroup(string $name): int
    {
        $this->connection->insert('hostgroup', ['hg_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkHostToGroup(int $hostId, int $groupId): void
    {
        $this->connection->insert('hostgroup_relation', [
            'host_host_id' => $hostId,
            'hostgroup_hg_id' => $groupId,
        ]);
    }

    private function linkHostToAcl(int $hostId, int $groupId): void
    {
        $this->realTimeConnection->insert('centreon_acl', [
            'group_id' => $groupId,
            'host_id' => $hostId,
        ]);
    }
}
