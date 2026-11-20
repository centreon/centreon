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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceGroup;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/service_groups. The provider rules are pinned by
 * ListServiceGroupsChoicesProviderLogicTest, the SQL by DbalServiceGroupRepositoryTest and the ACL tiers by
 * DbalResourceAccessRepositoryTest; the gate, the OpenAPI absence and the routing are pinned by
 * ServiceFormSelectorsWiringTest.
 */
final class ListServiceGroupsChoicesProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    private const ENDPOINT = '/api/configuration/services/service_groups';

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

    /**
     * A service read-write user needs no service-group role of its own; its ACL resource grants every
     * service group.
     */
    public function testItListsForAServiceReadWriteUser(): void
    {
        $name = "sg-{$this->tag}";
        $serviceGroupId = $this->insertServiceGroup($name);
        $username = bin2hex(random_bytes(8));
        $aclGroupId = $this->grantServiceTopologyRole(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::ACL_ACCESS_READ_WRITE,
            $this->tag,
        );
        $this->grantServiceGroups($aclGroupId, allServiceGroups: true, serviceGroupIds: []);
        $this->login($username);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertCount(1, $member);
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
        self::assertSame("/api/configuration/service_groups/{$serviceGroupId}", $member[0]['@id']);
        self::assertSame($serviceGroupId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
    }

    /**
     * The one end-to-end proof that the provider, the repository and ResourceAccessRepository scope service
     * groups on real ACL rows; the access tiers themselves are pinned by DbalResourceAccessRepositoryTest.
     */
    public function testARestrictedUserOnlySeesGrantedServiceGroups(): void
    {
        $grantedId = $this->insertServiceGroup("granted-{$this->tag}");
        $this->insertServiceGroup("other-{$this->tag}");
        $username = bin2hex(random_bytes(8));
        $aclGroupId = $this->grantServiceTopologyRole(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::ACL_ACCESS_READ_WRITE,
            $this->tag,
        );
        $this->grantServiceGroups($aclGroupId, allServiceGroups: false, serviceGroupIds: [$grantedId]);
        $this->login($username);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$grantedId], array_column($member, 'id'));
        self::assertSame(1, $response->toArray()['totalItems']);
    }

    private function insertServiceGroup(string $name): int
    {
        $this->connection->insert('servicegroup', ['sg_name' => $name, 'sg_activate' => '1']);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $serviceGroupIds
     */
    private function grantServiceGroups(int $aclGroupId, bool $allServiceGroups, array $serviceGroupIds): void
    {
        $this->connection->insert('acl_resources', [
            'acl_res_name' => "sg-scope-{$this->tag}",
            'acl_res_alias' => "sg-scope-{$this->tag}",
            'acl_res_activate' => '1',
            'all_servicegroups' => $allServiceGroups ? '1' : '0',
        ]);
        $aclResourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResourceId,
            'acl_group_id' => $aclGroupId,
        ]);
        foreach ($serviceGroupIds as $serviceGroupId) {
            $this->connection->insert('acl_resources_sg_relations', [
                'acl_res_id' => $aclResourceId,
                'sg_id' => $serviceGroupId,
            ]);
        }
    }
}
