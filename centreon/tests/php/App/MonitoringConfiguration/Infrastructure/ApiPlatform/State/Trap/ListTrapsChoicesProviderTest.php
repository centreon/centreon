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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Trap;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\GrantsServiceTopologyRole;
use Tests\App\Shared\ApiTestCase;

/**
 * Full-HTTP wiring of /configuration/services/traps. The provider rules are pinned by
 * ListTrapsChoicesProviderLogicTest and the SQL by DbalTrapRepositoryTest; the gate, the
 * OpenAPI absence and the routing are pinned by ServiceFormSelectorsWiringTest.
 */
final class ListTrapsChoicesProviderTest extends ApiTestCase
{
    use GrantsServiceTopologyRole;
    private const ENDPOINT = '/api/configuration/services/traps';

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
     * A service read-write user needs no trap role of its own. The vendor is a plain nested array: exactly
     * {id, name}, with no JSON-LD id or type key of its own.
     */
    public function testItListsForAServiceReadWriteUser(): void
    {
        $vendorName = "vendor-{$this->tag}";
        $this->connection->insert('traps_vendor', ['name' => $vendorName, 'alias' => $vendorName]);
        $vendorId = (int) $this->connection->lastInsertId();
        $name = "trap-{$this->tag}";
        $this->connection->insert('traps', ['traps_name' => $name, 'traps_oid' => '.1.3.6.1.4.1.1', 'manufacturer_id' => $vendorId]);
        $trapId = (int) $this->connection->lastInsertId();
        $this->loginAsServiceReadWriteUser($this->connection, $this->tag);

        $response = $this->request('GET', self::ENDPOINT, ['query' => ['name' => ['lk' => $this->tag]]]);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $member */
        $member = $response->toArray()['member'];
        self::assertCount(1, $member);
        self::assertEqualsCanonicalizing(['@id', '@type', 'id', 'name', 'vendor'], array_keys($member[0]));
        self::assertSame("/api/configuration/traps/{$trapId}", $member[0]['@id']);
        self::assertSame($trapId, $member[0]['id']);
        self::assertSame($name, $member[0]['name']);
        self::assertSame(['id' => $vendorId, 'name' => $vendorName], $member[0]['vendor']);
    }
}
