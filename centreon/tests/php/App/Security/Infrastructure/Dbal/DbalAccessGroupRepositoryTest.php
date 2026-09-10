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

namespace Tests\App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalAccessGroupRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalAccessGroupRepository $repository;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->repository = new DbalAccessGroupRepository($this->connection);
    }

    public function testContactWithNoAclGroupDoesNotBelongToTheNamedGroup(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        self::assertFalse($this->repository->userHasGroup($userId, 'customer_admin_acl'));
    }

    public function testContactDirectlyInTheNamedGroupBelongsToIt(): void
    {
        $contactId = $this->createContact('user-direct-member');
        $this->createAclGroup('customer_admin_acl', active: true, memberContactId: $contactId);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->userHasGroup($userId, 'customer_admin_acl'));
    }

    public function testContactInADifferentlyNamedGroupDoesNotBelongToTheNamedGroup(): void
    {
        $contactId = $this->createContact('user-other-group');
        $this->createAclGroup('some-other-group', active: true, memberContactId: $contactId);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->userHasGroup($userId, 'customer_admin_acl'));
    }

    public function testContactBelongingToTheNamedGroupOnlyThroughAnInactiveGroupDoesNotBelongToIt(): void
    {
        $contactId = $this->createContact('user-inactive-group');
        $this->createAclGroup('customer_admin_acl', active: false, memberContactId: $contactId);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->userHasGroup($userId, 'customer_admin_acl'));
    }

    public function testContactInTheNamedGroupThroughAContactGroupBelongsToIt(): void
    {
        $contactId = $this->createContact('user-via-contactgroup');
        $contactGroupId = $this->createContactGroup('cg-1', $contactId);
        $this->createAclGroup('customer_admin_acl', active: true, memberContactGroupId: $contactGroupId);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->userHasGroup($userId, 'customer_admin_acl'));
    }

    public function testContactWithNoAclGroupHasNoActiveGroupIds(): void
    {
        $userId = new UserId($this->createContact('user-no-acl-ids'));

        self::assertCount(0, $this->repository->findActiveGroupIdsForUser($userId));
    }

    public function testFindActiveGroupIdsForUserReturnsDirectAndIndirectMembershipsButNotInactiveOnes(): void
    {
        $contactId = $this->createContact('user-multi-membership');
        $directGroupId = $this->createAclGroup('direct-group', active: true, memberContactId: $contactId);
        $contactGroupId = $this->createContactGroup('cg-2', $contactId);
        $indirectGroupId = $this->createAclGroup('indirect-group', active: true, memberContactGroupId: $contactGroupId);
        $this->createAclGroup('inactive-group', active: false, memberContactId: $contactId);

        $userId = new UserId($contactId);

        $groupIds = array_map(
            static fn (AccessGroupId $id): int => $id->value,
            iterator_to_array($this->repository->findActiveGroupIdsForUser($userId)),
        );

        self::assertEqualsCanonicalizing([$directGroupId, $indirectGroupId], $groupIds);
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

    private function createContactGroup(string $name, int $memberContactId): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_activate' => '1',
        ]);
        $contactGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('contactgroup_contact_relation', [
            'contact_contact_id' => $memberContactId,
            'contactgroup_cg_id' => $contactGroupId,
        ]);

        return $contactGroupId;
    }

    private function createAclGroup(
        string $name,
        bool $active,
        ?int $memberContactId = null,
        ?int $memberContactGroupId = null,
    ): int {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => $name,
            'acl_group_alias' => $name,
            'acl_group_activate' => $active ? '1' : '0',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        if ($memberContactId !== null) {
            $this->connection->insert('acl_group_contacts_relations', [
                'acl_group_id' => $aclGroupId,
                'contact_contact_id' => $memberContactId,
            ]);
        }

        if ($memberContactGroupId !== null) {
            $this->connection->insert('acl_group_contactgroups_relations', [
                'acl_group_id' => $aclGroupId,
                'cg_cg_id' => $memberContactGroupId,
            ]);
        }

        return $aclGroupId;
    }
}
