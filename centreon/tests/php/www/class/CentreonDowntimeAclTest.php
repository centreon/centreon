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
    private const FORBIDDEN_HOST_GROUP_ID = 20;
    private const ALLOWED_SERVICE_ID = 100;
    private const FORBIDDEN_SERVICE_ID = 200;
    private const ALLOWED_SERVICE_GROUP_ID = 31;
    private const FORBIDDEN_SERVICE_GROUP_ID = 30;
    private const META_HOST_ID = 99;
    private const ALLOWED_META_ID = 7;
    private const FORBIDDEN_META_ID = 8;

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
        $this->userAcl->method('getServiceGroupAclConf')->willReturn([self::ALLOWED_SERVICE_GROUP_ID => 'sg-a']);
        $this->userAcl->method('getMetaServices')->willReturn([self::ALLOWED_META_ID => 'meta-a']);
        // Services are looked up per host: only service ALLOWED_SERVICE_ID of host ALLOWED_HOST_ID is allowed
        $this->userAcl->method('getHostServiceAclConf')->willReturnCallback(
            static fn (int $hostId): array => $hostId === self::ALLOWED_HOST_ID
                ? [self::ALLOWED_SERVICE_ID => 'service-a']
                : []
        );
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

    public function testDowntimeWithAnEnabledHostGroupOutsideTheAclIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(hostgroups: [$this->resource(self::FORBIDDEN_HOST_GROUP_ID)]),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeOnAnAllowedServiceIsAllowed(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [$this->service(self::ALLOWED_HOST_ID, self::ALLOWED_SERVICE_ID)]),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeWithAnEnabledServiceOutsideTheAclIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [
                $this->service(self::ALLOWED_HOST_ID, self::ALLOWED_SERVICE_ID),
                $this->service(self::ALLOWED_HOST_ID, self::FORBIDDEN_SERVICE_ID),
            ]),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testServiceAllowedOnAnotherHostIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [$this->service(self::FORBIDDEN_HOST_ID, self::ALLOWED_SERVICE_ID)]),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDisabledServiceOutsideTheAclDoesNotBlockTheDowntime(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [$this->service(self::FORBIDDEN_HOST_ID, self::FORBIDDEN_SERVICE_ID, false)]),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeWithoutRelationsIsAllowed(): void
    {
        $this->givenRelations([1 => $this->relations()]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeOnAnAllowedServiceGroupIsAllowed(): void
    {
        $this->givenRelations([
            1 => $this->relations(servicegroups: [$this->resource(self::ALLOWED_SERVICE_GROUP_ID)]),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeOnAnAllowedMetaServiceIsAllowed(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [$this->metaService(self::ALLOWED_META_ID)]),
        ]);

        $this->assertTrue($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testDowntimeWithAnEnabledMetaServiceOutsideTheAclIsRefused(): void
    {
        $this->givenRelations([
            1 => $this->relations(services: [$this->metaService(self::FORBIDDEN_META_ID)]),
        ]);

        $this->assertFalse($this->downtime->isAllowedForUser(1, $this->userAcl));
    }

    public function testServicesAreCheckedAgainstTheAclOfTheirOwnHost(): void
    {
        $userAcl = $this->createMock(CentreonACL::class);
        $userAcl->expects($this->exactly(2))
            ->method('getHostServiceAclConf')
            ->willReturnCallback(
                static fn (int $hostId): array => $hostId === self::ALLOWED_HOST_ID
                    ? [self::ALLOWED_SERVICE_ID => 'service-a']
                    : [self::FORBIDDEN_SERVICE_ID => 'service-b']
            );
        $this->givenRelations([
            1 => $this->relations(services: [
                $this->service(self::ALLOWED_HOST_ID, self::ALLOWED_SERVICE_ID),
                $this->service(self::FORBIDDEN_HOST_ID, self::FORBIDDEN_SERVICE_ID),
            ]),
            2 => $this->relations(services: [$this->service(self::FORBIDDEN_HOST_ID, self::ALLOWED_SERVICE_ID)]),
        ]);

        $this->assertSame([1], $this->downtime->filterIdsAllowedForUser([1, 2], $userAcl));
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
        $userAcl->expects($this->once())
            ->method('getHostGroupAclConf')
            ->willReturn([self::ALLOWED_HOST_GROUP_ID => 'hg-a']);
        $userAcl->expects($this->once())
            ->method('getHostServiceAclConf')
            ->willReturn([self::ALLOWED_SERVICE_ID => 'service-a']);
        $relations = $this->relations(
            hosts: [$this->resource(self::ALLOWED_HOST_ID)],
            hostgroups: [$this->resource(self::ALLOWED_HOST_GROUP_ID)],
            services: [$this->service(self::ALLOWED_HOST_ID, self::ALLOWED_SERVICE_ID)],
        );
        $this->givenRelations([1 => $relations, 2 => $relations]);

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
     * @param list<array{id: string, activated: string}> $services
     *
     * @return array<string, list<array{id: string, activated: string}>>
     */
    private function relations(
        array $hosts = [],
        array $hostgroups = [],
        array $servicegroups = [],
        array $services = [],
    ): array {
        return [
            'hosts' => $hosts,
            'hostgroups' => $hostgroups,
            'services' => $services,
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

    /**
     * Services are identified by '<hostId>-<serviceId>' in CentreonDowntime::getRelations()
     *
     * @return array{id: string, activated: string}
     */
    private function service(int $hostId, int $serviceId, bool $activated = true): array
    {
        return ['id' => $hostId . '-' . $serviceId, 'activated' => $activated ? '1' : '0'];
    }

    /**
     * Meta services carry their meta id in CentreonDowntime::getRelations()
     *
     * @return array{id: string, activated: string, meta_id: string}
     */
    private function metaService(int $metaId): array
    {
        return ['id' => self::META_HOST_ID . '-' . (1000 + $metaId), 'activated' => '1', 'meta_id' => (string) $metaId];
    }
}
