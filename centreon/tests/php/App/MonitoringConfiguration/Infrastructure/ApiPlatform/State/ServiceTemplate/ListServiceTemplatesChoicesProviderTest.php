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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\InsertsMonitoringConnectorTemplates;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/service_templates. The provider rules are pinned by
 * ListServiceTemplatesChoicesProviderLogicTest and the SQL by DbalServiceTemplateRepositoryTest; the gate, the
 * OpenAPI absence and the routing are pinned by ServiceFormSelectorsWiringTest.
 */
final class ListServiceTemplatesChoicesProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    use InsertsMonitoringConnectorTemplates;
    private const ENDPOINT = '/api/configuration/services/service_templates';

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

    protected function tearDown(): void
    {
        $this->dropMonitoringConnectorTables($this->connection);

        parent::tearDown();
    }

    /**
     * A service read-write user needs no service-template role of its own.
     */
    public function testItListsForAServiceReadWriteUser(): void
    {
        $name = "tpl-{$this->tag}";
        $this->connection->insert('service', ['service_description' => $name, 'service_register' => '0']);
        $serviceTemplateId = (int) $this->connection->lastInsertId();
        $this->loginAsServiceReadWriteUser($this->connection, $this->tag);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertCount(1, $member);
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name'], array_keys($member[0]));
        self::assertSame("/api/configuration/service_templates/{$serviceTemplateId}", $member[0]['@id']);
        self::assertSame($serviceTemplateId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
    }

    /**
     * Once the license host limit is reached (the test platform has no license manager), the templates
     * coming from Monitoring Connectors and the ones inheriting from them are excluded, as on the host form.
     */
    public function testItExcludesTheTemplatesTheLicenseNoLongerAllows(): void
    {
        $allowedName = "allowed-{$this->tag}";
        $this->connection->insert('service', ['service_description' => $allowedName, 'service_register' => '0']);
        $this->connection->insert('service', ['service_description' => "unlicensed-{$this->tag}", 'service_register' => '0']);

        $unlicensedId = (int) $this->connection->lastInsertId();
        $this->connection->insert('service', [
            'service_description' => "unlicensed-child-{$this->tag}",
            'service_register' => '0',
            'service_template_model_stm_id' => $unlicensedId,
        ]);
        $this->insertMonitoringConnectorServiceTemplates($this->connection, $unlicensedId);
        $this->loginAsServiceReadWriteUser($this->connection, $this->tag);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        self::assertSame([$allowedName], array_column((array) $response->toArray()['member'], 'name'));
    }
}
