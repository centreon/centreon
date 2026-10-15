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

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Exception\CredentialNotFoundException;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalCredentialRepository;
use App\Security\Infrastructure\Dbal\DbalCredentialTransformer;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @phpstan-import-type RowTypeAlias from DbalCredentialRepository
 */
final class DbalCredentialRepositoryTest extends KernelTestCase
{
    private const CUSTOMER_ADMIN_ACCESS_GROUP_NAME = 'customer_admin_acl';

    private Connection $connection;

    private DbalCredentialRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalCredentialRepository $repository */
        $repository = self::getContainer()->get(DbalCredentialRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testGetByUsernameReturnsCredential(): void
    {
        $username = 'admin';
        $credential = $this->repository->getByUsername($username);

        self::assertSame($username, $credential->identifier->value);
    }

    public function testGetByUsernameThrowsExceptionIfNotFound(): void
    {
        $this->expectException(CredentialNotFoundException::class);
        $this->repository->getByUsername('invalid-user');
    }

    public function testContactAdminIsSuperAdmin(): void
    {
        [, $username] = $this->createContact(admin: true);

        $credential = $this->repository->getByUsername($username);

        self::assertTrue($credential->isSuperAdmin());
        self::assertFalse($credential->isCloudAdmin());
        self::assertTrue($credential->isPermissionGranted(new Permission('can_read_host_groups')));
    }

    public function testCustomerAdminAclMemberIsCloudAdminOnCloud(): void
    {
        [$contactId, $username] = $this->createContact(admin: false);
        $this->addToCustomerAdminAccessGroup($contactId);

        $credential = $this->repositoryFor(isCloudPlatform: true)->getByUsername($username);

        self::assertFalse($credential->isSuperAdmin());
        self::assertTrue($credential->isCloudAdmin());
        self::assertTrue($credential->hasUnrestrictedResourceAccess());

        // Escaping resource scoping does not hand out menu access: with no ACL topology
        // rule of its own, the contact is granted nothing.
        self::assertFalse($credential->isPermissionGranted(new Permission('can_read_host_groups')));
    }

    public function testCustomerAdminAclMemberIsNotCloudAdminOnPrem(): void
    {
        [$contactId, $username] = $this->createContact(admin: false);
        $this->addToCustomerAdminAccessGroup($contactId);

        $credential = $this->repositoryFor(isCloudPlatform: false)->getByUsername($username);

        self::assertFalse($credential->isCloudAdmin());
        self::assertFalse($credential->hasUnrestrictedResourceAccess());
    }

    public function testPlainContactIsNoAdminOnCloud(): void
    {
        [, $username] = $this->createContact(admin: false);

        $credential = $this->repositoryFor(isCloudPlatform: true)->getByUsername($username);

        self::assertFalse($credential->isSuperAdmin());
        self::assertFalse($credential->isCloudAdmin());
    }

    /**
     * The container-built repository reads IS_CLOUD_PLATFORM, which is unset under test;
     * building it by hand is the only way to exercise both platform kinds.
     */
    private function repositoryFor(bool $isCloudPlatform): DbalCredentialRepository
    {
        /** @var TransformerInterface<RowTypeAlias, Credential> $transformer */
        $transformer = self::getContainer()->get(DbalCredentialTransformer::class);

        return new DbalCredentialRepository(
            $this->connection,
            $transformer,
            new DbalAccessGroupRepository($this->connection),
            $isCloudPlatform,
        );
    }

    /**
     * @return array{int, non-empty-string} contact id and alias
     */
    private function createContact(bool $admin): array
    {
        $alias = 'credential-' . bin2hex(random_bytes(8));

        $this->connection->insert('contact', [
            'contact_name' => $alias,
            'contact_alias' => $alias,
            'contact_admin' => $admin ? '1' : '0',
            'contact_register' => '1',
            'contact_activate' => '1',
            'contact_email' => $alias . '@email.com',
        ]);

        return [(int) $this->connection->lastInsertId(), $alias];
    }

    private function addToCustomerAdminAccessGroup(int $contactId): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => self::CUSTOMER_ADMIN_ACCESS_GROUP_NAME,
            'acl_group_alias' => self::CUSTOMER_ADMIN_ACCESS_GROUP_NAME,
            'acl_group_activate' => '1',
        ]);

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => (int) $this->connection->lastInsertId(),
            'contact_contact_id' => $contactId,
        ]);
    }
}
