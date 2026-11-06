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

use CentreonACL;
use CentreonDB;
use CentreonDowntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CentreonDowntimeAclTest extends TestCase
{
    private const ALLOWED_HOST_ID = 1;
    private const FORBIDDEN_HOST_ID = 2;
    private const ALLOWED_HOST_GROUP_ID = 10;
    private const FORBIDDEN_SERVICE_GROUP_ID = 30;

    private CentreonDowntime&MockObject $downtime;

    private CentreonACL&MockObject $userAcl;

    protected function setUp(): void
    {
        $this->downtime = $this->getMockBuilder(CentreonDowntime::class)
            ->setConstructorArgs([$this->createMock(CentreonDB::class)])
            ->onlyMethods(['getRelations'])
            ->getMock();

        $this->userAcl = $this->createMock(CentreonACL::class);
        $this->userAcl->method('getHostAclConf')->willReturn([self::ALLOWED_HOST_ID => 'host-a']);
        $this->userAcl->method('getHostGroupAclConf')->willReturn([self::ALLOWED_HOST_GROUP_ID => 'hg-a']);
        $this->userAcl->method('getServiceGroupAclConf')->willReturn([]);
    }

    public function testDowntimeOnAllowedResourcesIsAllowed(): void
    {
        $this->givenRelations([
            1 => $this->relations(
                hosts: [$this->resource(self::ALLOWED_HOST_ID)],
                hostgroups: [$this->resource(self::ALLOWED_HOST_GROUP_ID)],
            ),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeWithAnEnabledHostOutsideTheAclIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(
                hosts: [$this->resource(self::ALLOWED_HOST_ID), $this->resource(self::FORBIDDEN_HOST_ID)],
            ),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeWithAnEnabledServiceGroupOutsideTheAclIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(servicegroups: [$this->resource(self::FORBIDDEN_SERVICE_GROUP_ID)]),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDisabledResourceOutsideTheAclDoesNotBlockTheDowntime(): void
    {
        $this->givenRelations([
            1 => $this->relations(
                hosts: [$this->resource(self::ALLOWED_HOST_ID), $this->resource(self::FORBIDDEN_HOST_ID, false)],
            ),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testOnlyAllowedDowntimesAreKeptFromASelection(): void
    {
        $this->givenRelations([
            1 => $this->relations(hosts: [$this->resource(self::ALLOWED_HOST_ID)]),
            2 => $this->relations(hosts: [$this->resource(self::FORBIDDEN_HOST_ID)]),
            3 => $this->relations(
                hosts: [$this->resource(self::ALLOWED_HOST_ID), $this->resource(self::FORBIDDEN_HOST_ID)],
            ),
        ]);

        $this->assertSame([1], $this->downtime->filterIdsAllowedForUser(['1', '2', '3'], $this->userAcl));
    }

    public function testAclResourcesAreQueriedOnceForAMassAction(): void
    {
        $userAcl = $this->createMock(CentreonACL::class);
        $userAcl->expects($this->once())
            ->method('getHostAclConf')
            ->willReturn([self::ALLOWED_HOST_ID => 'host-a']);
        $this->givenRelations([
            1 => $this->relations(hosts: [$this->resource(self::ALLOWED_HOST_ID)]),
            2 => $this->relations(hosts: [$this->resource(self::ALLOWED_HOST_ID)]),
        ]);

        $this->assertSame([1, 2], $this->downtime->filterIdsAllowedForUser([1, 2], $userAcl));
    }

    /**
     * @param array<int, array<string, list<array{id: string, activated: string}>>> $relationsByDowntimeId
     */
    private function givenRelations(array $relationsByDowntimeId): void
    {
        $this->downtime->method('getRelations')
            ->willReturnCallback(static fn (int $downtimeId): array => $relationsByDowntimeId[$downtimeId]);
    }

    /**
     * @param list<array{id: string, activated: string}> $hosts
     * @param list<array{id: string, activated: string}> $hostgroups
     * @param list<array{id: string, activated: string}> $servicegroups
     *
     * @return array<string, list<array{id: string, activated: string}>>
     */
    private function relations(array $hosts = [], array $hostgroups = [], array $servicegroups = []): array
    {
        return [
            'hosts' => $hosts,
            'hostgroups' => $hostgroups,
            'services' => [],
            'servicegroups' => $servicegroups,
        ];
    }

    /**
     * Same format as the records returned by CentreonDowntime::getRelations()
     *
     * @return array{id: string, activated: string}
     */
    private function resource(int $id, bool $activated = true): array
    {
        return ['id' => (string) $id, 'activated' => $activated ? '1' : '0'];
    }
}
