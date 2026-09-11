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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroup;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalContactGroupRepositoryTest extends KernelTestCase
{
    private ContactGroupRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var ContactGroupRepository $repository */
        $repository = self::getContainer()->get(ContactGroupRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testFindAllForAnAdminReturnsEveryContactGroup(): void
    {
        $expected = $this->countContactGroups();

        $result = $this->repository->findAll(new ContactGroupCriteria());

        self::assertCount($expected, iterator_to_array($result));
    }

    public function testFindAllWithPaginationReturnsAPaginator(): void
    {
        $total = $this->countContactGroups();
        if ($total === 0) {
            self::markTestSkipped('No contact group in the dataset.');
        }

        $result = $this->repository->findAll((new ContactGroupCriteria())->withPagination(1, 1));

        self::assertInstanceOf(Paginator::class, $result);
        self::assertCount(1, iterator_to_array($result));
        self::assertSame($total, $result->getTotalItems());
    }

    public function testFindAllScopedToAMemberReturnsOnlyTheirContactGroups(): void
    {
        $suffix = bin2hex(random_bytes(6));

        $this->connection->insert('contact', [
            'contact_name' => "viewer_{$suffix}",
            'contact_alias' => "viewer_{$suffix}",
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => "viewer_{$suffix}@email.com",
        ]);
        $viewerId = (int) $this->connection->lastInsertId();

        $memberGroupId = $this->insertContactGroup("member_{$suffix}");
        $unrelatedGroupId = $this->insertContactGroup("unrelated_{$suffix}");

        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $memberGroupId,
            'contact_contact_id' => $viewerId,
        ]);

        $result = $this->repository->findAll(
            (new ContactGroupCriteria())->withViewerId(new UserId($viewerId))
        );

        $names = array_map(
            static fn (ContactGroup $contactGroup): string => $contactGroup->name->value,
            iterator_to_array($result)
        );

        self::assertContains("member_{$suffix}", $names);
        self::assertNotContains("unrelated_{$suffix}", $names);
    }

    private function countContactGroups(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM contactgroup');

        return is_numeric($count) ? (int) $count : 0;
    }

    private function insertContactGroup(string $name): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_type' => 'local',
            'cg_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
