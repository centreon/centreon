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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyHostServiceDuplicatorWrapper;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCloner;

/**
 * The re-link decision runs on the configuration connection and is tested against the database here.
 * Whether a service is cloned is delegated to the {@see FakeServiceCloner}, so the exclusive-clone
 * path is asserted by what the wrapper hands it rather than by running the legacy procedural clone,
 * which has no new-architecture equivalent and is covered end to end on the CDE.
 */
final class LegacyHostServiceDuplicatorWrapperTest extends KernelTestCase
{
    private Connection $connection;

    private FakeServiceCloner $serviceCloner;

    private LegacyHostServiceDuplicatorWrapper $wrapper;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->serviceCloner = new FakeServiceCloner();
        $this->wrapper = new LegacyHostServiceDuplicatorWrapper($this->connection, $this->serviceCloner);
    }

    public function testASharedServiceIsRelinkedOntoTheCopyNotCloned(): void
    {
        $this->insertHost(9501, 'wrapper-source');
        $this->insertHost(9502, 'wrapper-copy');
        $this->insertHost(9503, 'wrapper-other');
        $this->insertService(8801, 'shared-service');
        // The service is linked to the source and to another host, so it is shared (host_count = 2).
        $this->linkServiceToHost(9501, 8801);
        $this->linkServiceToHost(9503, 8801);

        $this->wrapper->duplicate(sourceHostId: new HostId(9501), newHostId: new HostId(9502), duplicatedBy: 1);

        self::assertSame(
            1,
            $this->countServiceLinks(9502, 8801),
            'the shared service is re-linked onto the copy, keeping its identity',
        );
        self::assertSame([], $this->clonedServiceIds(), 'a shared service is not cloned');
    }

    public function testASourceWithNoServiceInsertsNothingAndClonesNothing(): void
    {
        $this->insertHost(9601, 'wrapper-empty-source');
        $this->insertHost(9602, 'wrapper-empty-copy');

        $this->wrapper->duplicate(sourceHostId: new HostId(9601), newHostId: new HostId(9602), duplicatedBy: 1);

        self::assertSame(0, $this->countServiceLinks(9602), 'nothing is linked onto a copy whose source has no service');
        self::assertSame([], $this->clonedServiceIds(), 'the cloner is called with an empty list');
    }

    public function testAnExclusiveServiceIsPassedToTheClonerNotRelinked(): void
    {
        $this->insertHost(9701, 'wrapper-exclusive-source');
        $this->insertHost(9702, 'wrapper-exclusive-copy');
        $this->insertService(8802, 'exclusive-service');
        // The service is linked to the source only, so it is exclusive (host_count = 1) and must be cloned.
        $this->linkServiceToHost(9701, 8802);

        $this->wrapper->duplicate(sourceHostId: new HostId(9701), newHostId: new HostId(9702), duplicatedBy: 7);

        self::assertSame(
            0,
            $this->countServiceLinks(9702),
            'an exclusive service is not re-linked onto the copy: cloning it is the legacy step, not a re-link',
        );
        self::assertSame(
            [['serviceIds' => [8802], 'newHostId' => 9702, 'duplicatedBy' => 7]],
            $this->serviceCloner->cloneCalls,
            'the exclusive service is handed to the cloner, with the copy and the acting contact',
        );
    }

    public function testAServiceSharedWithAHostGroupIsRelinkedNotCloned(): void
    {
        $this->insertHost(9801, 'wrapper-hg-source');
        $this->insertHost(9802, 'wrapper-hg-copy');
        $this->insertHostGroup(7701, 'wrapper-hg');
        $this->insertService(8803, 'hostgroup-shared-service');
        // The service is linked to the source host and to a host group: COUNT(*) = 2, so it is shared.
        $this->linkServiceToHost(9801, 8803);
        $this->linkServiceToHostGroup(7701, 8803);

        $this->wrapper->duplicate(sourceHostId: new HostId(9801), newHostId: new HostId(9802), duplicatedBy: 1);

        self::assertSame(
            1,
            $this->countServiceLinks(9802, 8803),
            'a service shared through a host group counts as shared and is re-linked, not cloned',
        );
        self::assertSame([], $this->clonedServiceIds());
    }

    private function insertHost(int $id, string $name): void
    {
        $this->connection->insert('host', ['host_id' => $id, 'host_name' => $name, 'host_register' => '1']);
    }

    private function insertHostGroup(int $id, string $name): void
    {
        $this->connection->insert('hostgroup', ['hg_id' => $id, 'hg_name' => $name]);
    }

    private function insertService(int $id, string $description): void
    {
        $this->connection->insert('service', ['service_id' => $id, 'service_description' => $description, 'service_register' => '1']);
    }

    private function linkServiceToHost(int $hostId, int $serviceId): void
    {
        $this->connection->insert('host_service_relation', ['host_host_id' => $hostId, 'service_service_id' => $serviceId]);
    }

    private function linkServiceToHostGroup(int $hostGroupId, int $serviceId): void
    {
        $this->connection->insert('host_service_relation', ['hostgroup_hg_id' => $hostGroupId, 'service_service_id' => $serviceId]);
    }

    /**
     * @return list<int>
     */
    private function clonedServiceIds(): array
    {
        $serviceIds = [];
        foreach ($this->serviceCloner->cloneCalls as $call) {
            foreach ($call['serviceIds'] as $serviceId) {
                $serviceIds[] = $serviceId;
            }
        }

        return $serviceIds;
    }

    private function countServiceLinks(int $hostId, ?int $serviceId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM host_service_relation WHERE host_host_id = :hostId';
        $parameters = ['hostId' => $hostId];
        if ($serviceId !== null) {
            $sql .= ' AND service_service_id = :serviceId';
            $parameters['serviceId'] = $serviceId;
        }

        $count = $this->connection->fetchOne($sql, $parameters);
        self::assertIsScalar($count);

        return (int) $count;
    }
}
