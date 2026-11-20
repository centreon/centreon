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

namespace Tests\www\class;

use Adaptation\Database\Connection\Collection\QueryParameters;
use CentreonACL;
use CentreonDB;
use CentreonDBInstance;
use CentreonDBStatement;
use PHPUnit\Framework\TestCase;

// getMyHostName(), getMyHostServices(), getMyServiceHosts() and getMyServiceName() live outside the Composer classmap.
require_once __DIR__ . '/../../../../www/include/common/common-Func.php';

class CentreonACLTest extends TestCase
{
    private const HOST_ID = 42;
    private const SERVICE_ID = 7;

    /** @var list<array{string, array<string, mixed>}> statements run on the storage database, with their parameters */
    private array $storageStatements = [];

    private mixed $previousPearDb = null;

    protected function setUp(): void
    {
        // The legacy helpers read the configuration through the global $pearDB.
        $this->previousPearDb = $GLOBALS['pearDB'] ?? null;
        $GLOBALS['pearDB'] = $this->configurationDb();

        $storageDb = $this->createMock(CentreonDB::class);
        $storageDb->method('executeStatement')->willReturnCallback(
            function (string $query, ?QueryParameters $parameters = null): int {
                $this->storageStatements[] = [preg_replace('/\s+/', ' ', $query) ?? $query, self::values($parameters)];

                return 1;
            }
        );
        // The access groups that have rows for the host or the service: 10 and 20.
        $storageDb->method('fetchFirstColumn')->willReturnCallback(
            function (string $query, ?QueryParameters $parameters = null): array {
                $this->storageStatements[] = [$query, self::values($parameters)];

                return [10, 20];
            }
        );
        $this->injectDbInstance('dbCentreonInstance', $this->configurationDb());
        $this->injectDbInstance('dbCentreonStorageInstance', $storageDb);
    }

    protected function tearDown(): void
    {
        $GLOBALS['pearDB'] = $this->previousPearDb;
        $reflection = new \ReflectionClass(CentreonDBInstance::class);
        $reflection->getProperty('dbCentreonInstance')->setValue(null, null);
        $reflection->getProperty('dbCentreonStorageInstance')->setValue(null, null);
    }

    public function testAHostAddedByANonAdminRemovesTheFingerprintOfTheGroupsThatGotIt(): void
    {
        $this->nonAdminAcl()->updateACL(['type' => 'HOST', 'id' => self::HOST_ID, 'action' => 'ADD']);

        self::assertSame(
            [
                ['INSERT INTO centreon_acl (host_id, service_id, group_id) VALUES (:host_id, NULL, :group_id)', ['host_id' => self::HOST_ID, 'group_id' => 10]],
                ['INSERT INTO centreon_acl (host_id, service_id, group_id) VALUES (:host_id, NULL, :group_id)', ['host_id' => self::HOST_ID, 'group_id' => 20]],
                ['SELECT DISTINCT group_id FROM centreon_acl WHERE host_id = :hostId', ['hostId' => self::HOST_ID]],
                [
                    'DELETE FROM centreon_acl_checksum WHERE group_id IN (:groupId0, :groupId1)',
                    ['groupId0' => 10, 'groupId1' => 20],
                ],
            ],
            $this->storageStatements
        );
    }

    public function testAServiceAddedByANonAdminRemovesTheFingerprintOfTheGroupsThatGotIt(): void
    {
        $this->nonAdminAcl()->updateACL(['type' => 'SERVICE', 'id' => self::SERVICE_ID, 'action' => 'ADD']);

        // The two INSERTs, then the invalidation for the only host of the service, found by host and service ids.
        self::assertCount(4, $this->storageStatements);
        self::assertSame(
            [
                [
                    'SELECT DISTINCT group_id FROM centreon_acl WHERE host_id = :hostId AND service_id = :serviceId',
                    ['hostId' => self::HOST_ID, 'serviceId' => self::SERVICE_ID],
                ],
                [
                    'DELETE FROM centreon_acl_checksum WHERE group_id IN (:groupId0, :groupId1)',
                    ['groupId0' => 10, 'groupId1' => 20],
                ],
            ],
            array_slice($this->storageStatements, 2)
        );
    }

    /**
     * @return array<string, mixed> the parameter values, by name
     */
    private static function values(?QueryParameters $parameters): array
    {
        $values = [];
        foreach ($parameters?->values() ?? [] as $parameter) {
            $values[$parameter->getName()] = $parameter->getValue();
        }

        return $values;
    }

    /**
     * A non-admin user who belongs to the access groups 10 and 20.
     */
    private function nonAdminAcl(): CentreonACL
    {
        $acl = (new \ReflectionClass(CentreonACL::class))->newInstanceWithoutConstructor();
        $acl->admin = false;
        (new \ReflectionProperty(CentreonACL::class, 'accessGroups'))->setValue($acl, [10 => 'group-10', 20 => 'group-20']);

        return $acl;
    }

    /**
     * Configuration database: host 42 has no service, and service 7 is attached to host 42 only.
     */
    private function configurationDb(): CentreonDB
    {
        $db = $this->createMock(CentreonDB::class);
        $db->method('query')->willReturn($this->createMock(CentreonDBStatement::class));
        $db->method('prepare')->willReturnCallback(function (string $query): CentreonDBStatement {
            $rows = match (true) {
                str_contains($query, 'SELECT host_name FROM host') => [['host_name' => 'host-42']],
                str_contains($query, 'SELECT DISTINCT host_host_id FROM host_service_relation') => [['host_host_id' => self::HOST_ID]],
                str_contains($query, 'SELECT service_description, service_template_model_stm_id FROM service') => [
                    ['service_description' => 'service-7', 'service_template_model_stm_id' => null],
                ],
                default => [],
            };
            $statement = $this->createMock(CentreonDBStatement::class);
            $statement->method('fetchRow')->willReturnOnConsecutiveCalls(...[...$rows, false]);

            return $statement;
        });

        return $db;
    }

    private function injectDbInstance(string $property, CentreonDB $db): void
    {
        $reflection = new \ReflectionClass(CentreonDBInstance::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('instance')->setValue($instance, $db);
        $reflection->getProperty($property)->setValue(null, $instance);
    }
}
