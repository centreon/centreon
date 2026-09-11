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

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContact;
use App\MonitoringConfiguration\Domain\Repository\Criteria\NotificationContactCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalNotificationContactRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\NotificationContactTransformer;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalNotificationContactRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalNotificationContactRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalNotificationContactRepository(
            $this->connection,
            new NotificationContactTransformer(),
            new DbalAccessGroupRepository($this->connection),
        );

        // unique per test run so assertions are isolated from any pre-seeded contacts
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsAllRegisteredContactsWithoutAViewer(): void
    {
        $name = "contact-{$this->tag}";
        $this->insertContact($name);

        $names = $this->names($this->repository->findAll());

        self::assertContains($name, $names);
    }

    public function testFindAllExcludesUnregisteredContacts(): void
    {
        $registeredName = "registered-{$this->tag}";
        $this->insertContact($registeredName);
        $unregisteredName = "unregistered-{$this->tag}";
        $this->insertContact($unregisteredName, registered: false);

        $names = $this->names($this->repository->findAll());

        self::assertContains($registeredName, $names);
        self::assertNotContains($unregisteredName, $names, 'A non-registered contact (contact_register = 0) must never appear.');
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertContact("match-{$this->tag}");
        $this->insertContact("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllFiltersByAliasUsingLike(): void
    {
        $name = "byalias-{$this->tag}";
        $this->insertContact($name, alias: "findme-{$this->tag}");
        $this->insertContact("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withName("findme-{$this->tag}")));

        self::assertSame([$name], $names, 'The search must also match contact_alias: users often know a contact by their alias rather than their full name.');
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertContact("pg-{$this->tag}-A");
        $this->insertContact("pg-{$this->tag}-B");
        $this->insertContact("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded contacts cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new NotificationContactCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by contact_name (A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindAllRestrictsToContactsDirectlyLinkedToViewerAccessGroup(): void
    {
        $accessibleName = "direct-in-{$this->tag}";
        $accessibleId = $this->insertContact($accessibleName);
        $inaccessibleName = "direct-out-{$this->tag}";
        $this->insertContact($inaccessibleName);

        $viewerId = $this->createViewer();
        $aclGroupId = $this->createAccessGroupForViewer($viewerId);
        $this->linkContactToAccessGroup($accessibleId, $aclGroupId);

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withViewerId(new UserId($viewerId))));

        self::assertContains($accessibleName, $names);
        self::assertNotContains($inaccessibleName, $names, 'A contact with no ACL group relation at all is excluded.');
    }

    public function testFindAllRestrictsToContactsBelongingToAContactGroupLinkedToViewerAccessGroup(): void
    {
        $accessibleName = "viagroup-in-{$this->tag}";
        $accessibleId = $this->insertContact($accessibleName);
        $inaccessibleName = "viagroup-out-{$this->tag}";
        $this->insertContact($inaccessibleName);

        $viewerId = $this->createViewer();
        $aclGroupId = $this->createAccessGroupForViewer($viewerId);
        $contactGroupId = $this->createContactGroup("cg-{$this->tag}");
        $this->addContactToContactGroup($accessibleId, $contactGroupId);
        $this->linkContactGroupToAccessGroup($contactGroupId, $aclGroupId);

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withViewerId(new UserId($viewerId))));

        self::assertContains($accessibleName, $names);
        self::assertNotContains(
            $inaccessibleName,
            $names,
            'A contact whose contact group is not linked to the viewer ACL group is excluded.'
        );
    }

    public function testFindAllReturnsNoContactForARestrictedViewerWithNoAccessGroup(): void
    {
        $this->insertContact("anycontact-{$this->tag}");

        // a viewer whose own contact record carries no ACL group relation at all
        $viewerId = $this->createViewer();

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withViewerId(new UserId($viewerId))));

        self::assertSame([], $names, 'A viewer with no active Access Group is restricted and sees no contact.');
    }

    public function testFindAllReturnsAContactOnceWhenItMatchesBothAclBranches(): void
    {
        $name = "bothbranches-{$this->tag}";
        $contactId = $this->insertContact($name);

        $viewerId = $this->createViewer();
        $aclGroupId = $this->createAccessGroupForViewer($viewerId);
        // branch (a): linked directly to the viewer's Access Group
        $this->linkContactToAccessGroup($contactId, $aclGroupId);
        // branch (b), simultaneously: also a member of a contact group linked to the same Access Group
        $contactGroupId = $this->createContactGroup("cg-{$this->tag}");
        $this->addContactToContactGroup($contactId, $contactGroupId);
        $this->linkContactGroupToAccessGroup($contactGroupId, $aclGroupId);

        $names = $this->names($this->repository->findAll((new NotificationContactCriteria())->withViewerId(new UserId($viewerId))));

        self::assertSame(1, array_count_values($names)[$name] ?? 0, 'A contact matching both ACL branches at once must still appear exactly once.');
    }

    /**
     * @param \IteratorAggregate<int, NotificationContact>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (NotificationContact $contact): string => $contact->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertContact(string $name, ?string $alias = null, bool $registered = true): int
    {
        $this->connection->insert('contact', [
            'contact_name' => $name,
            'contact_alias' => $alias ?? $name,
            'contact_admin' => '0',
            'contact_register' => $registered ? '1' : '0',
            'contact_activate' => '1',
            'contact_email' => $name . '@email.com',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function createViewer(): int
    {
        return $this->insertContact("viewer-{$this->tag}-" . Uuid::v4()->toRfc4122());
    }

    private function createAccessGroupForViewer(int $viewerContactId): int
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => 'group-' . Uuid::v4()->toRfc4122(),
            'acl_group_alias' => 'group-' . Uuid::v4()->toRfc4122(),
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $viewerContactId,
        ]);

        return $aclGroupId;
    }

    private function linkContactToAccessGroup(int $contactId, int $aclGroupId): void
    {
        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);
    }

    private function createContactGroup(string $name): int
    {
        $this->connection->insert('contactgroup', [
            'cg_name' => $name,
            'cg_alias' => $name,
            'cg_activate' => '1',
            'cg_type' => 'local',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function addContactToContactGroup(int $contactId, int $contactGroupId): void
    {
        $this->connection->insert('contactgroup_contact_relation', [
            'contactgroup_cg_id' => $contactGroupId,
            'contact_contact_id' => $contactId,
        ]);
    }

    private function linkContactGroupToAccessGroup(int $contactGroupId, int $aclGroupId): void
    {
        $this->connection->insert('acl_group_contactgroups_relations', [
            'acl_group_id' => $aclGroupId,
            'cg_cg_id' => $contactGroupId,
        ]);
    }
}
