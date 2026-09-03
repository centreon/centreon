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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroComment;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroExpression;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Repository\Criteria\PollerCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalPollerRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPollerRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalPollerRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalPollerRepository $repository */
        $repository = self::getContainer()->get(DbalPollerRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $connection->insert('nagios_server', ['id' => 1, 'name' => 'Central', 'localhost' => '1', 'ns_activate' => '1', 'ns_ip_address' => '127.0.0.1', 'uid' => 100000000000001]);
        $connection->insert('cfg_resource', ['resource_id' => 1, 'resource_name' => '$USER1$', 'resource_line' => '/usr/lib64/nagios/plugins/', 'resource_comment' => 'path to plugins', 'resource_activate' => '1', 'is_password' => 0]);
        $connection->insert('cfg_resource', ['resource_id' => 2, 'resource_name' => '$CENTREONPLUGINS$', 'resource_line' => '/usr/lib64/nagios/plugins/', 'resource_comment' => 'Centreon Plugin Path', 'resource_activate' => '1', 'is_password' => 0]);
        $connection->insert('cfg_resource_instance_relations', ['resource_id' => 1, 'instance_id' => 1]);
        $connection->insert('cfg_resource_instance_relations', ['resource_id' => 2, 'instance_id' => 1]);
    }

    public function testAddCreatesPlatformTopologyEntry(): void
    {
        $this->connection->insert('platform_topology', [
            'address' => '127.0.0.1',
            'name' => 'Central',
            'type' => 'central',
            'server_id' => 1,
            'pending' => '0',
        ]);

        $poller = new Poller(
            id: null,
            name: new PollerName('MyPoller'),
            address: new PollerAddress('192.168.1.10'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(100000000000002),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineInformation: new EngineInformation(),
            brokerInformation: new BrokerInformation(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
            centralAddress: new CentralAddress('10.0.0.1'),
        );

        $this->repository->add($poller);

        $topology = $this->connection->fetchAssociative(
            'SELECT * FROM platform_topology WHERE server_id = :serverId',
            ['serverId' => $poller->id()->value],
        );

        self::assertIsArray($topology);
        self::assertSame('192.168.1.10', $topology['address']);
        self::assertSame('10.0.0.1', $topology['central_address']);
        self::assertSame('MyPoller', $topology['name']);
        self::assertSame('poller', $topology['type']);
        self::assertSame('0', $topology['pending']);

        $centralTopology = $this->connection->fetchAssociative(
            "SELECT id FROM platform_topology WHERE type = 'central'",
        );
        self::assertIsArray($centralTopology);
        self::assertIsScalar($centralTopology['id']);
        self::assertIsScalar($topology['parent_id']);
        self::assertSame((int) $centralTopology['id'], (int) $topology['parent_id']);
    }

    public function testItFindAllByGlobalMacro(): void
    {
        $pollers = $this->repository->findAllByGlobalMacro(
            new GlobalMacro(
                new GlobalMacroId(1),
                new GlobalMacroName('$USER1$'),
                new GlobalMacroExpression('/usr/lib64/nagios/plugins/'),
                new GlobalMacroComment('path to plugins'),
                false,
                false,
                new Collection([], Poller::class)
            )
        );
        self::assertCount(1, $pollers);

        /** @var Poller $poller */
        $poller = $pollers->toArray()[0];
        self::assertCount(
            2,
            $poller->globalMacros,
            'Poller #1 is linked to both $USER1$ and $CENTREONPLUGINS$ global macros, batched hydration must attach them by their own poller id, not lose them.'
        );
        $globalMacroNames = array_map(
            static fn (GlobalMacro $globalMacro): string => $globalMacro->name->value,
            $poller->globalMacros->toArray()
        );
        self::assertEqualsCanonicalizing(['$USER1$', '$CENTREONPLUGINS$'], $globalMacroNames);
    }

    public function testFindAllReturnsAllPollersWhenNoCriteria(): void
    {
        $this->insertPoller(2, 'Poller-A');
        $this->insertPoller(3, 'Poller-B');

        $pollers = $this->repository->findAll();

        self::assertCount(3, $pollers);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertPoller(2, 'Poller-A');
        $this->insertPoller(3, 'Poller-B');

        $pollers = $this->repository->findAll((new PollerCriteria())->withName('Poller-'));

        self::assertCount(2, $pollers);
        self::assertEqualsCanonicalizing(
            ['Poller-A', 'Poller-B'],
            array_map(static fn (Poller $poller): string => $poller->name->value, iterator_to_array($pollers))
        );
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertPoller(2, 'Poller-A');
        $this->insertPoller(3, 'Poller-B');

        $pollers = $this->repository->findAll((new PollerCriteria())->withPagination(1, 2));

        self::assertInstanceOf(Paginator::class, $pollers);
        self::assertCount(2, $pollers);
        self::assertSame(3, $pollers->getTotalItems());
    }

    public function testFindAllExcludesCentralNotRegisteredAsARemoteServer(): void
    {
        $this->insertPoller(2, 'Poller-A', isCentral: false);

        $pollers = $this->repository->findAll((new PollerCriteria())->withExcludeUnknownCentral(true));

        self::assertCount(1, $pollers);
        self::assertSame('Poller-A', iterator_to_array($pollers)[0]->name->value);
    }

    public function testFindAllKeepsCentralWhenItsAddressIsARegisteredRemoteServer(): void
    {
        $this->insertPoller(2, 'Poller-A', isCentral: false);
        $this->connection->insert('remote_servers', [
            'ip' => '127.0.0.1', // matches the Central poller's ns_ip_address from setUp
            'version' => '24.10',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'server_id' => 2,
        ]);

        $pollers = $this->repository->findAll((new PollerCriteria())->withExcludeUnknownCentral(true));

        self::assertCount(2, $pollers);
    }

    public function testFindAllRestrictsToAccessiblePollersForARestrictedViewer(): void
    {
        $this->insertPoller(2, 'Poller-A');
        $this->insertPoller(3, 'Poller-B');
        $viewerId = new UserId($this->createRestrictedContact(accessiblePollerIds: [2]));

        $pollers = $this->repository->findAll((new PollerCriteria())->withViewerId($viewerId));

        self::assertCount(1, $pollers);
        self::assertSame('Poller-A', iterator_to_array($pollers)[0]->name->value);
    }

    private function insertPoller(int $id, string $name, bool $isCentral = false): void
    {
        $this->connection->insert('nagios_server', [
            'id' => $id,
            'name' => $name,
            'localhost' => $isCentral ? '1' : '0',
            'ns_activate' => '1',
            'ns_ip_address' => "10.0.0.{$id}",
            'uid' => 200000000000000 + $id,
        ]);
    }

    /**
     * @param list<int> $accessiblePollerIds
     */
    private function createRestrictedContact(array $accessiblePollerIds): int
    {
        $this->connection->insert('contact', [
            'contact_name' => 'restricted-viewer',
            'contact_alias' => 'restricted-viewer',
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => 'restricted-viewer@email.com',
        ]);
        $contactId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'restricted-group',
            'acl_group_alias' => 'restricted-group',
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'restricted-resource',
            'acl_res_alias' => 'restricted-resource',
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($accessiblePollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }

        return $contactId;
    }
}
