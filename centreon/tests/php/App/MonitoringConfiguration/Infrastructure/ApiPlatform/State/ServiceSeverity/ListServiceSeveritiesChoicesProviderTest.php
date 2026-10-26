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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceSeverity;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/service_severities. The provider rules are pinned by
 * ListServiceSeveritiesChoicesProviderLogicTest, the SQL by DbalServiceSeverityRepositoryTest and the
 * ACL tiers by DbalResourceAccessRepositoryTest; the gate, the OpenAPI absence and the routing are pinned by
 * ServiceFormSelectorsWiringTest.
 */
#[Group('wip')]
final class ListServiceSeveritiesChoicesProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    private const ENDPOINT = '/api/configuration/services/service_severities';

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
     * A service read-write user needs no service-severity role of its own; its ACL resource grants the
     * listed severity, and a severity it is not granted stays out.
     */
    public function testItListsForAServiceReadWriteUser(): void
    {
        $name = "sv-{$this->tag}";
        $severityId = $this->insertServiceCategory($name, level: 1);
        $this->insertServiceCategory("not-granted-{$this->tag}", level: 2);
        $this->loginWithGrantedServiceCategories([$severityId]);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertSame([$severityId], array_column($member, 'id'), 'The severity the user is not granted is absent.');
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
        self::assertSame("/api/configuration/service_severities/{$severityId}", $member[0]['@id']);
        self::assertSame($name, $member[0]['name']);
    }

    /**
     * A service category carrying a level is a service severity.
     */
    private function insertServiceCategory(string $name, ?int $level = null): int
    {
        $this->connection->insert('service_categories', [
            'sc_name' => $name,
            'sc_description' => $name,
            'level' => $level,
            'sc_activate' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Logs in a new service read-write user whose active ACL resource grants exactly these service
     * categories (severities included).
     *
     * @param list<int> $serviceCategoryIds
     */
    private function loginWithGrantedServiceCategories(array $serviceCategoryIds): void
    {
        $username = bin2hex(random_bytes(8));
        $aclGroupId = $this->grantServiceTopologyRole(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::ACL_ACCESS_READ_WRITE,
            $this->tag,
        );
        $this->connection->insert('acl_resources', [
            'acl_res_name' => "sc-scope-{$this->tag}",
            'acl_res_alias' => "sc-scope-{$this->tag}",
            'acl_res_activate' => '1',
        ]);
        $aclResourceId = (int) $this->connection->lastInsertId();
        $this->connection->insert('acl_res_group_relations', [
            'acl_res_id' => $aclResourceId,
            'acl_group_id' => $aclGroupId,
        ]);
        foreach ($serviceCategoryIds as $serviceCategoryId) {
            $this->connection->insert('acl_resources_sc_relations', [
                'acl_res_id' => $aclResourceId,
                'sc_id' => $serviceCategoryId,
            ]);
        }
        $this->login($username);
    }
}
