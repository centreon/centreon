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

use CentreonDB;
use CentreonDBStatement;
use CentreonService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CentreonServiceTest extends TestCase
{
    /** Statement returned by the select2 query */
    private CentreonDBStatement&MockObject $select2Statement;

    /** SQL passed to prepare() by getObjectForSelect2() */
    private string $preparedQuery = '';

    /** Values bound to the select2 query, by placeholder */
    private array $boundValues = [];

    private CentreonService $service;

    protected function setUp(): void
    {
        $this->select2Statement = $this->createMock(CentreonDBStatement::class);
        $this->select2Statement->method('bindValue')->willReturnCallback(
            function (string $param, mixed $value): bool {
                $this->boundValues[$param] = $value;

                return true;
            }
        );

        // Pollers loaded by CentreonInstance when CentreonService is built
        $pollerStatement = $this->createMock(CentreonDBStatement::class);
        $pollerStatement->method('fetchRow')->willReturn(false);

        $db = $this->createMock(CentreonDB::class);
        $db->method('query')->willReturn($pollerStatement);
        $db->method('prepare')->willReturnCallback(
            function (string $query): CentreonDBStatement {
                $this->preparedQuery = $query;

                return $this->select2Statement;
            }
        );

        $dbMon = $this->createMock(CentreonDB::class);
        $dbMon->method('query')->willReturn($pollerStatement);

        $this->service = new CentreonService($db, $dbMon);
    }

    public function testAnomalyDetectionServicesOfRegularHostsAreKeptOnReload(): void
    {
        $this->select2Statement->method('fetch')->willReturnOnConsecutiveCalls(
            ['host_id' => '10', 'host_name' => 'H', 'service_id' => '100', 'service_description' => 'S1'],
            ['host_id' => '10', 'host_name' => 'H', 'service_id' => '300', 'service_description' => 'AD1'],
            false
        );

        $services = $this->service->getObjectForSelect2(['10-100', '10-300'], []);

        $this->assertStringContainsString('s.service_register IN (:serviceRegister, :adServiceRegister)', $this->preparedQuery);
        $this->assertSame('1', $this->boundValues[':hostRegister']);
        $this->assertSame('1', $this->boundValues[':serviceRegister']);
        $this->assertSame('3', $this->boundValues[':adServiceRegister']);
        $this->assertSame(
            [
                ['id' => '10-100', 'text' => 'H - S1'],
                ['id' => '10-300', 'text' => 'H - AD1'],
            ],
            $services
        );
    }

    public function testServiceTemplatesOfHostTemplatesStillOnlyMatchTemplates(): void
    {
        $this->select2Statement->method('fetch')->willReturn(false);

        $this->service->getObjectForSelect2(['1-200'], [], '0');

        $this->assertSame('0', $this->boundValues[':hostRegister']);
        $this->assertSame('0', $this->boundValues[':serviceRegister']);
        $this->assertSame('0', $this->boundValues[':adServiceRegister']);
    }

    public function testHostGroupServicesStillOnlyMatchRegularServices(): void
    {
        $this->select2Statement->method('fetch')->willReturn(false);

        $this->service->getObjectForSelect2(['5-100'], ['hostgroup' => true]);

        $this->assertStringContainsString("s.service_register = '1'", $this->preparedQuery);
        $this->assertStringNotContainsString("'3'", $this->preparedQuery);
    }
}
