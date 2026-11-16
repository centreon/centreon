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
use CentreonDB;
use CentreonDowntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CentreonDowntimeEnabledRelationsTest extends TestCase
{
    private const ENABLED_ID = '1';
    private const DISABLED_ID = '2';
    private const ENABLED_SERVICE = '1-10';
    private const DISABLED_SERVICE = '1-20';

    private CentreonDowntime&MockObject $downtime;

    protected function setUp(): void
    {
        $this->downtime = $this->getMockBuilder(CentreonDowntime::class)
            ->setConstructorArgs([$this->createMock(CentreonDB::class)])
            ->onlyMethods(['getRelations', 'findEnabledResourceIds'])
            ->getMock();
        $this->downtime->method('findEnabledResourceIds')->willReturnCallback(
            static fn (string $resourceType, array $resourceIds): array => array_values(array_intersect(
                $resourceIds,
                [self::ENABLED_ID, self::ENABLED_SERVICE]
            ))
        );
    }

    public function testNewDowntimeOnEnabledResourcesIsAccepted(): void
    {
        $this->assertTrue($this->downtime->areSubmittedRelationsEnabled(
            $this->submitted(
                hosts: [self::ENABLED_ID],
                hostgroups: [self::ENABLED_ID],
                services: [self::ENABLED_SERVICE],
                servicegroups: [self::ENABLED_ID],
            ),
            null,
        ));
    }

    /**
     * @param array<string, list<string>> $submittedRelations
     */
    #[DataProvider('provideADisabledResourceOfEachType')]
    public function testNewDowntimeOnADisabledResourceIsRefused(array $submittedRelations): void
    {
        $this->assertFalse($this->downtime->areSubmittedRelationsEnabled($submittedRelations, null));
    }

    /**
     * @return iterable<string, array{array<string, list<string>>}>
     */
    public static function provideADisabledResourceOfEachType(): iterable
    {
        $enabledRelations = [
            'hosts' => [self::ENABLED_ID],
            'hostgroups' => [self::ENABLED_ID],
            'services' => [self::ENABLED_SERVICE],
            'servicegroups' => [self::ENABLED_ID],
        ];

        yield 'host' => [['hosts' => [self::ENABLED_ID, self::DISABLED_ID]] + $enabledRelations];

        yield 'host group' => [['hostgroups' => [self::DISABLED_ID]] + $enabledRelations];

        yield 'service' => [['services' => [self::ENABLED_SERVICE, self::DISABLED_SERVICE]] + $enabledRelations];

        yield 'service group' => [['servicegroups' => [self::DISABLED_ID]] + $enabledRelations];
    }

    public function testKeepingADisabledResourceAlreadyLinkedOnEditIsAccepted(): void
    {
        $this->downtime->method('getRelations')->willReturn([
            'hosts' => [['id' => self::DISABLED_ID, 'activated' => '0']],
            'hostgroups' => [],
            'services' => [['id' => self::DISABLED_SERVICE, 'activated' => '0']],
            'servicegroups' => [],
        ]);

        $this->assertTrue($this->downtime->areSubmittedRelationsEnabled(
            $this->submitted(
                hosts: [self::ENABLED_ID, self::DISABLED_ID],
                services: [self::DISABLED_SERVICE],
            ),
            1,
        ));
    }

    public function testAddingADisabledResourceOnEditIsRefused(): void
    {
        $this->downtime->method('getRelations')->willReturn([
            'hosts' => [['id' => self::ENABLED_ID, 'activated' => '1']],
            'hostgroups' => [],
            'services' => [],
            'servicegroups' => [],
        ]);

        $this->assertFalse($this->downtime->areSubmittedRelationsEnabled(
            $this->submitted(hosts: [self::ENABLED_ID, self::DISABLED_ID]),
            1,
        ));
    }

    public function testOnlyTheEnabledServicesAmongTheSubmittedOnesAreFound(): void
    {
        $database = $this->createMock(CentreonDB::class);
        $database->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->callback(static fn (string $query): bool => str_contains($query, "h.host_activate = '1'")
                    && str_contains($query, "s.service_activate = '1'")),
                $this->callback(static fn (QueryParameters $queryParameters): bool => $queryParameters->length() === 3)
            )
            ->willReturn(['1-10']);

        $this->assertFalse((new CentreonDowntime($database))->areSubmittedRelationsEnabled(
            $this->submitted(services: ['1-10', '1-20']),
            null,
        ));
    }

    public function testMalformedIdsAreRefusedWithoutQueryingTheDatabase(): void
    {
        $database = $this->createMock(CentreonDB::class);
        $database->expects($this->never())->method('fetchFirstColumn');
        $downtime = new CentreonDowntime($database);

        $this->assertFalse($downtime->areSubmittedRelationsEnabled($this->submitted(hosts: ['1 OR 1=1']), null));
        $this->assertFalse($downtime->areSubmittedRelationsEnabled($this->submitted(services: ['10']), null));
    }

    /**
     * Same format as the relations submitted by the downtime form
     *
     * @param list<string> $hosts
     * @param list<string> $hostgroups
     * @param list<string> $services '<hostId>-<serviceId>' identifiers
     * @param list<string> $servicegroups
     *
     * @return array<string, list<string>>
     */
    private function submitted(
        array $hosts = [],
        array $hostgroups = [],
        array $services = [],
        array $servicegroups = [],
    ): array {
        return [
            'hosts' => $hosts,
            'hostgroups' => $hostgroups,
            'services' => $services,
            'servicegroups' => $servicegroups,
        ];
    }
}
