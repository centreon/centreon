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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\NotificationContact;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\Shared\ApiTestCase;
use Webmozart\Assert\Assert;

final class ListNotificationContactsProviderTest extends ApiTestCase
{
    private const BASE_ENDPOINT = '/api/configuration/contacts';

    // topology pages whose hierarchy builds ROLE_CONFIGURATION_USERS_CONTACTS__USERS_R
    // (Configuration > Users > "Contacts / Users"), bridged to NotificationContactPermissionEnum::CanRead
    // via DbalCredentialTransformer::LEGACY_PERMISSION_MAP.
    private const NOTIFICATION_CONTACT_READ_TOPOLOGY_PAGES = [6, 603, 60301];

    private Connection $connection;

    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItRequiresAuthentication(): void
    {
        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(401);
    }

    public function testItIsForbiddenForUserWithoutSufficientAcl(): void
    {
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($this->connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', self::BASE_ENDPOINT);
        self::assertResponseStatusCodeSame(403);
    }

    public function testItListsContactsAsAdminWithOnlyIdAndName(): void
    {
        $this->insertContact("contact-A-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "contact-A-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(NotificationContactResource::class);
        self::assertJsonContains([
            'member' => [
                ['name' => "contact-A-{$this->tag}"],
            ],
        ]);

        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
    }

    public function testItFiltersContactsByNameUsingLikeOperator(): void
    {
        $this->insertContact("match-{$this->tag}");
        $this->insertContact("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "match-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertCount(1, (array) $response->toArray()['member']);
        self::assertJsonContains([
            'member' => [
                ['name' => "match-{$this->tag}"],
            ],
        ]);
    }

    public function testItFiltersContactsByAliasUsingLikeOperator(): void
    {
        $name = "byalias-{$this->tag}";
        $this->insertContact($name, alias: "findme-{$this->tag}");
        $this->insertContact("other-{$this->tag}");

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => "findme-{$this->tag}"]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            [$name],
            array_column((array) $response->toArray()['member'], 'name'),
            'The search must also match contact_alias: users often know a contact by their alias rather than their full name.'
        );
    }

    public function testItExcludesUnregisteredContacts(): void
    {
        $registeredName = "registered-{$this->tag}";
        $this->insertContact($registeredName);
        $unregisteredName = "unregistered-{$this->tag}";
        $this->insertContact($unregisteredName, registered: false);

        $this->login();

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame([$registeredName], array_column((array) $response->toArray()['member'], 'name'));
    }

    public function testItPaginatesContacts(): void
    {
        $this->insertContact("pg-{$this->tag}-A");
        $this->insertContact("pg-{$this->tag}-B");
        $this->insertContact("pg-{$this->tag}-C");

        $this->login();

        // scope to our own rows via the name filter so pre-seeded contacts cannot skew the total
        $response = $this->request('GET', self::BASE_ENDPOINT, [
            'query' => ['name' => ['lk' => "pg-{$this->tag}-"], 'page' => '1', 'itemsPerPage' => '2'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(2, (array) $response->toArray()['member']);
        self::assertEquals(3, $response->toArray()['totalItems']);
    }

    public function testItRestrictsListingToContactsDirectlyLinkedToAnAccessGroupForARestrictedUser(): void
    {
        $accessibleName = "direct-in-{$this->tag}";
        $accessibleId = $this->insertContact($accessibleName);
        $this->insertContact("direct-out-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantNotificationContactReadTopologyRole($contactId);
        $aclGroupId = $this->findAclGroupForContact($contactId);
        $this->linkContactToAccessGroup($accessibleId, $aclGroupId);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            [$accessibleName],
            array_column((array) $response->toArray()['member'], 'name'),
            'Only the contact directly linked to the viewer Access Group is returned.'
        );
    }

    public function testItRestrictsListingToContactsViaAContactGroupForARestrictedUser(): void
    {
        $accessibleName = "viagroup-in-{$this->tag}";
        $accessibleId = $this->insertContact($accessibleName);
        $this->insertContact("viagroup-out-{$this->tag}");

        $username = bin2hex(random_bytes(8));
        $contactId = $this->createNonAdminContact($username);
        $this->grantNotificationContactReadTopologyRole($contactId);
        $aclGroupId = $this->findAclGroupForContact($contactId);
        $contactGroupId = $this->createContactGroup("cg-{$this->tag}");
        $this->addContactToContactGroup($accessibleId, $contactGroupId);
        $this->linkContactGroupToAccessGroup($contactGroupId, $aclGroupId);

        $this->login($username);

        $response = $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(
            [$accessibleName],
            array_column((array) $response->toArray()['member'], 'name'),
            'Only the contact whose contact group is linked to the viewer Access Group is returned.'
        );
    }

    public function testItIgnoresAnEmptyNameFilter(): void
    {
        $this->insertContact("empty-{$this->tag}");

        $this->login();

        // an empty "like" value must be ignored (not applied, not rejected, not a 500 from the VO)
        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => ['lk' => '']]]);
        self::assertResponseIsSuccessful();
    }

    public function testItRejectsAScalarNameFilter(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['name' => "contact-{$this->tag}"]]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testItRejectsZeroItemsPerPage(): void
    {
        $this->login();

        $this->request('GET', self::BASE_ENDPOINT, ['query' => ['itemsPerPage' => '0']]);
        self::assertResponseStatusCodeSame(400);
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

    private function createNonAdminContact(string $alias): int
    {
        $this->createApiUser($this->connection, $alias, admin: false);

        $contactId = $this->connection->fetchOne(
            'SELECT contact_id FROM contact WHERE contact_alias = :alias',
            ['alias' => $alias]
        );
        Assert::notFalse($contactId);
        Assert::scalar($contactId);

        return (int) $contactId;
    }

    private function grantNotificationContactReadTopologyRole(int $contactId): void
    {
        $this->connection->insert('acl_groups', [
            'acl_group_name' => "topology-group-{$this->tag}",
            'acl_group_alias' => "topology-group-{$this->tag}",
            'acl_group_activate' => '1',
        ]);
        $aclGroupId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_contacts_relations', [
            'acl_group_id' => $aclGroupId,
            'contact_contact_id' => $contactId,
        ]);

        $this->connection->insert('acl_topology', [
            'acl_topo_name' => "topology-rule-{$this->tag}",
            'acl_topo_alias' => "topology-rule-{$this->tag}",
            'acl_topo_activate' => '1',
        ]);
        $aclTopoId = (int) $this->connection->lastInsertId();

        $this->connection->insert('acl_group_topology_relations', [
            'acl_group_id' => $aclGroupId,
            'acl_topology_id' => $aclTopoId,
        ]);

        foreach (self::NOTIFICATION_CONTACT_READ_TOPOLOGY_PAGES as $topologyPage) {
            $topologyId = $this->connection->fetchOne(
                'SELECT topology_id FROM topology WHERE topology_page = :page',
                ['page' => $topologyPage]
            );
            Assert::notFalse($topologyId, "topology_page {$topologyPage} not found in fixtures");
            Assert::scalar($topologyId);

            $this->connection->insert('acl_topology_relations', [
                'topology_topology_id' => (int) $topologyId,
                'acl_topo_id' => $aclTopoId,
                'access_right' => 2, // read-only
            ]);
        }
    }

    private function findAclGroupForContact(int $contactId): int
    {
        $aclGroupId = $this->connection->fetchOne(
            'SELECT acl_group_id FROM acl_group_contacts_relations WHERE contact_contact_id = :contactId',
            ['contactId' => $contactId]
        );
        Assert::notFalse($aclGroupId);
        Assert::scalar($aclGroupId);

        return (int) $aclGroupId;
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
