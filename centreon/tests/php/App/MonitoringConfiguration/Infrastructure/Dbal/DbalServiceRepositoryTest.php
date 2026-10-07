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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalServiceTransformer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalServiceRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalServiceRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->repository = new DbalServiceRepository($this->connection, new DbalServiceTransformer());
    }

    public function testFindExclusivelyLinkedToHostIdReturnsAnEmptyCollectionWhenTheHostHasNoService(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
    }

    public function testFindExclusivelyLinkedToHostIdReturnsAServiceDirectlyAttachedToTheHost(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $this->createService('ping', $hostId);

        $services = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId));

        self::assertCount(1, $services);
        $service = $services->toArray()[0];
        self::assertSame('ping', $service->name->value);
        self::assertSame($hostId, $service->hostId->value);
        self::assertSame([], $service->macros);
    }

    public function testFindExclusivelyLinkedToHostIdExcludesAServiceSharedWithAnotherHost(): void
    {
        $pollerId = $this->createPoller('Central');
        $hostId = $this->createHost('server-01', $pollerId);
        $otherHostId = $this->createHost('server-02', $pollerId);
        $serviceId = $this->createService('shared', $hostId);
        $this->linkServiceToHost($serviceId, $otherHostId);

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($otherHostId)));
    }

    public function testFindExclusivelyLinkedToHostIdHydratesTheServicesMacros(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $this->createMacro($serviceId, 'TOKEN', 'secret-value', isPassword: true, description: 'API token');

        $services = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId));

        $macros = $services->toArray()[0]->macros;
        self::assertCount(1, $macros);
        self::assertSame('TOKEN', $macros[0]->name->value);
        self::assertSame('secret-value', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
        self::assertSame('API token', $macros[0]->description);
    }

    public function testRemoveDeletesTheServiceRow(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $this->createService('ping', $hostId);
        $service = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray()[0];

        $this->repository->remove($service);

        self::assertCount(0, $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId)));
    }

    public function testRemoveDeletesADependencyWhoseLastParentServiceIsRemoved(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $dependencyId = $this->createDependency();
        $this->connection->insert('dependency_serviceParent_relation', [
            'dependency_dep_id' => $dependencyId,
            'service_service_id' => $serviceId,
            'host_host_id' => $hostId,
        ]);
        $service = $this->repository->findExclusivelyLinkedToHostId(new HostId($hostId))->toArray()[0];

        $this->repository->remove($service);

        self::assertFalse($this->dependencyExists($dependencyId));
    }

    public function testRemoveKeepsADependencyThatStillHasAnotherParentService(): void
    {
        $hostId = $this->createHost('server-01', $this->createPoller('Central'));
        $serviceId = $this->createService('ping', $hostId);
        $otherServiceId = $this->createService('http', $hostId);
        $dependencyId = $this->createDependency();
        foreach ([$serviceId, $otherServiceId] as $id) {
            $this->connection->insert('dependency_serviceParent_relation', [
                'dependency_dep_id' => $dependencyId,
                'service_service_id' => $id,
                'host_host_id' => $hostId,
            ]);
        }
        $service = new Service(id: new ServiceId($serviceId), name: new ServiceName('ping'), hostId: new HostId($hostId));

        $this->repository->remove($service);

        self::assertTrue($this->dependencyExists($dependencyId));
    }

    public function testRemoveDoesNotDeleteAServiceTemplate(): void
    {
        // findExclusivelyLinkedToHostId() never returns a template's id today (templates carry no
        // host_service_relation row), but the guard mirrors legacy DbWriteServiceRepository's own
        // `AND service_register = '1'` regardless of caller, so it's exercised directly here.
        $templateId = $this->createServiceTemplate('web-check-template');
        $template = new Service(id: new ServiceId($templateId), name: new ServiceName('web-check-template'), hostId: new HostId(1));

        $this->repository->remove($template);

        self::assertNotFalse($this->connection->fetchOne(
            'SELECT service_id FROM service WHERE service_id = :id',
            ['id' => $templateId],
        ));
    }

    private function createDependency(): int
    {
        $this->connection->insert('dependency', ['dep_name' => 'dependency-' . bin2hex(random_bytes(4))]);

        return (int) $this->connection->lastInsertId();
    }

    private function dependencyExists(int $dependencyId): bool
    {
        return $this->connection->fetchOne('SELECT dep_id FROM dependency WHERE dep_id = ?', [$dependencyId]) !== false;
    }

    private function createPoller(string $name): int
    {
        $this->connection->insert('nagios_server', [
            'name' => $name,
            'uid' => random_int(1, PHP_INT_MAX),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createHost(string $name, int $pollerId): int
    {
        $this->connection->insert('host', [
            'host_name' => $name,
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'host_register' => '1',
        ]);
        $hostId = (int) $this->connection->lastInsertId();

        $this->connection->insert('ns_host_relation', [
            'host_host_id' => $hostId,
            'nagios_server_id' => $pollerId,
        ]);

        return $hostId;
    }

    private function createService(string $description, int $hostId): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => '1',
        ]);
        $serviceId = (int) $this->connection->lastInsertId();

        $this->linkServiceToHost($serviceId, $hostId);

        return $serviceId;
    }

    private function createServiceTemplate(string $description): int
    {
        $this->connection->insert('service', [
            'service_description' => $description,
            'service_register' => '0',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function linkServiceToHost(int $serviceId, int $hostId): void
    {
        $this->connection->insert('host_service_relation', [
            'host_host_id' => $hostId,
            'service_service_id' => $serviceId,
        ]);
    }

    private function createMacro(int $serviceId, string $name, string $value, bool $isPassword, ?string $description = null): void
    {
        $this->connection->insert('on_demand_macro_service', [
            'svc_macro_name' => '$_SERVICE' . $name . '$',
            'svc_macro_value' => $value,
            'is_password' => $isPassword ? '1' : '0',
            'description' => $description,
            'svc_svc_id' => $serviceId,
        ]);
    }
}
