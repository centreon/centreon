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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalResourceAccessRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalResourceAccessRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalResourceAccessRepository $repository */
        $repository = self::getContainer()->get(DbalResourceAccessRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->connection->insert('nagios_server', ['id' => 101, 'name' => 'Poller-101', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.101', 'uid' => 200000000000101]);
        $this->connection->insert('nagios_server', ['id' => 102, 'name' => 'Poller-102', 'localhost' => '0', 'ns_activate' => '1', 'ns_ip_address' => '10.0.0.102', 'uid' => 200000000000102]);
    }

    public function testUserWithNoAclGroupHasAccessToAllPollers(): void
    {
        $userId = new UserId($this->createContact('user-no-acl'));

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertNull($this->repository->findAccessiblePollerIds($userId));
    }

    public function testUserWithAclResourceHavingNoPollerRestrictionHasAccessToAllPollers(): void
    {
        $contactId = $this->createContact('user-unrestricted');
        $this->linkContactToAclResource($contactId, restrictToPollerIds: []);

        $userId = new UserId($contactId);

        self::assertTrue($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(102), $userId));
        self::assertNull($this->repository->findAccessiblePollerIds($userId));
    }

    public function testUserWithAclResourceRestrictedToASinglePollerSeesOnlyThatPoller(): void
    {
        $contactId = $this->createContact('user-restricted');
        $this->linkContactToAclResource($contactId, restrictToPollerIds: [101]);

        $userId = new UserId($contactId);

        self::assertFalse($this->repository->hasAccessToAllPollers($userId));
        self::assertTrue($this->repository->hasAccessToPoller(new PollerId(101), $userId));
        self::assertFalse($this->repository->hasAccessToPoller(new PollerId(102), $userId));

        $accessiblePollerIds = $this->repository->findAccessiblePollerIds($userId);
        self::assertNotNull($accessiblePollerIds);
        self::assertEquals([101], array_map(static fn (PollerId $id): int => $id->value, $accessiblePollerIds));
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
     * @param list<int> $restrictToPollerIds empty means the ACL resource carries no poller restriction at all
     */
    private function linkContactToAclResource(int $contactId, array $restrictToPollerIds): void
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

        foreach ($restrictToPollerIds as $pollerId) {
            $this->connection->insert('acl_resources_poller_relations', [
                'acl_res_id' => $aclResId,
                'poller_id' => $pollerId,
            ]);
        }
    }
}
