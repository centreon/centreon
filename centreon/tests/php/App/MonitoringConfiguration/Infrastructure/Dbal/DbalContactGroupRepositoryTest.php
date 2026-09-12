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

        // Wrap each test in a transaction so the fixtures it inserts never leak into the dataset.
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
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

        $viewerId = $this->insertNonAdminContact("viewer_{$suffix}");
        $memberGroupId = $this->insertContactGroup("member_{$suffix}");
        $this->insertContactGroup("unrelated_{$suffix}");

        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $memberGroupId,
            'contact_contact_id' => $viewerId,
        ]);

        $names = $this->scopedNames($viewerId);

        self::assertContains("member_{$suffix}", $names);
        self::assertNotContains("unrelated_{$suffix}", $names);
    }

    public function testFindAllScopedViaAnAccessGroupReturnsOnlyReachableContactGroups(): void
    {
        $suffix = bin2hex(random_bytes(6));

        $viewerId = $this->insertNonAdminContact("viewer_{$suffix}");
        $reachableGroupId = $this->insertContactGroup("reachable_{$suffix}");
        $this->insertContactGroup("unreachable_{$suffix}");

        // The viewer belongs to an active access group that is granted the reachable contact group.
        $this->connection->insert('acl_groups', [
            'acl_group_name' => "acl_{$suffix}",
            'acl_group_alias' => "acl_{$suffix}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $viewerId,
        ]);
        $this->connection->insert('acl_group_contactgroups_relations', [
            'acl_group_id' => $aclGroupId,
            'cg_cg_id' => $reachableGroupId,
        ]);

        $names = $this->scopedNames($viewerId);

        self::assertContains("reachable_{$suffix}", $names);
        self::assertNotContains("unreachable_{$suffix}", $names);
    }

    public function testFindAllFiltersByNameUsingEquals(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->insertContactGroup("exact_{$suffix}");
        $this->insertContactGroup("other_{$suffix}");

        $names = $this->names($this->repository->findAll(
            (new ContactGroupCriteria())->withName("exact_{$suffix}", ContactGroupCriteria::OPERATOR_EQUAL)
        ));

        self::assertSame(["exact_{$suffix}"], $names);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->insertContactGroup("needle_{$suffix}");
        $this->insertContactGroup("haystack_{$suffix}");

        $names = $this->names($this->repository->findAll(
            (new ContactGroupCriteria())->withName("needle_{$suffix}", ContactGroupCriteria::OPERATOR_LIKE)
        ));

        self::assertContains("needle_{$suffix}", $names);
        self::assertNotContains("haystack_{$suffix}", $names);
    }

    public function testFindAllFiltersById(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $wantedId = $this->insertContactGroup("wanted_{$suffix}");
        $this->insertContactGroup("skipped_{$suffix}");

        $names = $this->names($this->repository->findAll(
            (new ContactGroupCriteria())->withId($wantedId, ContactGroupCriteria::OPERATOR_EQUAL)
        ));

        self::assertSame(["wanted_{$suffix}"], $names);
    }

    public function testFindAllScopedIgnoresNonRegisteredContactMembership(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $viewerId = $this->insertNonAdminContact("viewer_{$suffix}");
        $groupId = $this->insertContactGroup("group_{$suffix}");

        // A membership held through a non-registered contact must not grant visibility.
        $this->connection->update('contact', ['contact_register' => '0'], ['contact_id' => $viewerId]);
        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $groupId,
            'contact_contact_id' => $viewerId,
        ]);

        self::assertNotContains("group_{$suffix}", $this->scopedNames($viewerId));
    }

    public function testFindAllScopedToAViewerWithoutAnyAccessReturnsNothing(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $viewerId = $this->insertNonAdminContact("viewer_{$suffix}");
        $this->insertContactGroup("group_{$suffix}");

        // No access group, no membership: the viewer sees nothing — and the paginated call must
        // still return an (empty) Paginator, not a bare Collection.
        $criteria = (new ContactGroupCriteria())->withViewerId(new UserId($viewerId));

        self::assertCount(0, iterator_to_array($this->repository->findAll($criteria)));

        $paginated = $this->repository->findAll($criteria->withPagination(1, 10));
        self::assertInstanceOf(Paginator::class, $paginated);
        self::assertCount(0, iterator_to_array($paginated));
        self::assertSame(0, $paginated->getTotalItems());
    }

    /**
     * @return list<string>
     */
    private function scopedNames(int $viewerId): array
    {
        return $this->names($this->repository->findAll(
            (new ContactGroupCriteria())->withViewerId(new UserId($viewerId))
        ));
    }

    /**
     * @param \IteratorAggregate<int, ContactGroup>&\Countable $result
     *
     * @return list<string>
     */
    private function names(iterable $result): array
    {
        return array_values(array_map(
            static fn (ContactGroup $contactGroup): string => $contactGroup->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertNonAdminContact(string $name): int
    {
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $name,
            'contact_admin' => '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => "{$name}@email.com",
        ]);

        return (int) $this->connection->lastInsertId();
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
