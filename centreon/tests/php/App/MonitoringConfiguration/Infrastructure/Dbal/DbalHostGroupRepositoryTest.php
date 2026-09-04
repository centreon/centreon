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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostGroupCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostGroupRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostGroupTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalHostGroupRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalHostGroupRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // Not yet reachable through the container: no ApiPlatform Provider consumes
        // HostGroupRepository yet, so the compiled container prunes it as unused.
        $this->repository = new DbalHostGroupRepository(
            $this->connection,
            new DbalHostGroupTransformer(),
            new DbalResourceAccessRepository($this->connection),
        );
    }

    public function testFindAllReturnsAllHostGroups(): void
    {
        $this->insertHostGroup(301, 'HostGroup-A');
        $this->insertHostGroup(302, 'HostGroup-B');

        $hostGroups = iterator_to_array($this->repository->findAll());

        self::assertCount(2, $hostGroups);
        self::assertContainsOnlyInstancesOf(HostGroup::class, $hostGroups);
    }

    public function testFindAllFiltersByNameUsingLikeOperator(): void
    {
        $this->insertHostGroup(301, 'Linux-Servers');
        $this->insertHostGroup(302, 'Windows-Servers');

        $criteria = (new HostGroupCriteria())->withName('Linux');

        $hostGroups = iterator_to_array($this->repository->findAll($criteria));

        self::assertCount(1, $hostGroups);
        self::assertSame(['Linux-Servers'], $this->extractNames($hostGroups));
    }

    public function testFindAllPaginates(): void
    {
        $this->insertHostGroup(301, 'HostGroup-A');
        $this->insertHostGroup(302, 'HostGroup-B');
        $this->insertHostGroup(303, 'HostGroup-C');

        $criteria = (new HostGroupCriteria())->withPagination(page: 2, itemsPerPage: 1);

        $paginator = $this->repository->findAll($criteria);

        self::assertInstanceOf(InMemoryPaginator::class, $paginator);

        self::assertCount(1, $paginator);
        self::assertSame(3, $paginator->getTotalItems());
        self::assertSame(['HostGroup-B'], $this->extractNames(iterator_to_array($paginator)));
    }

    public function testFindAllRestrictsToAccessibleHostGroupsForARestrictedViewer(): void
    {
        $this->insertHostGroup(301, 'HostGroup-A');
        $this->insertHostGroup(302, 'HostGroup-B');

        $contactId = $this->createContact('restricted-user');
        $this->restrictContactToHostGroups($contactId, [302]);

        $criteria = (new HostGroupCriteria())->withViewerId(new UserId($contactId));

        $hostGroups = iterator_to_array($this->repository->findAll($criteria));

        self::assertCount(1, $hostGroups);
        self::assertSame(['HostGroup-B'], $this->extractNames($hostGroups));
    }

    private function insertHostGroup(int $id, string $name): void
    {
        $this->connection->insert('hostgroup', ['hg_id' => $id, 'hg_name' => $name]);
    }

    /**
     * @param array<HostGroup> $hostGroups
     *
     * @return list<string>
     */
    private function extractNames(array $hostGroups): array
    {
        return array_values(array_map(static fn (HostGroup $hostGroup): string => $hostGroup->name->value, $hostGroups));
    }

    private function createContact(string $alias): int
    {
        $this->connection->insert('contact', [
            'contact_name' => $alias,
            'contact_alias' => $alias,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $alias . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $hostGroupIds
     */
    private function restrictContactToHostGroups(int $contactId, array $hostGroupIds): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'group-' . $contactId,
            'acl_group_alias' => 'group-' . $contactId,
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_resources', [
            'acl_res_name' => 'resource-' . $contactId,
            'acl_res_alias' => 'resource-' . $contactId,
            'acl_res_activate' => '1',
        ]);
        $aclResId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResId,
            'acl_group_id' => $aclGroupId,
        ]);

        foreach ($hostGroupIds as $hostGroupId) {
            $this->connection->insert('acl_resources_hg_relations', [
                'acl_res_id' => $aclResId,
                'hg_hg_id' => $hostGroupId,
            ]);
        }
    }
}
