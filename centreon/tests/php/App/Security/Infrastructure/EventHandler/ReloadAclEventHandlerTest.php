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

namespace Tests\App\Security\Infrastructure\EventHandler;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Event\HostCreated;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\EventHandler\ReloadAclEventHandler;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Security\Infrastructure\Double\FakeAccessGroupRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;

final class ReloadAclEventHandlerTest extends TestCase
{
    public function testItSeedsCentreonAclAndFlagsOnlyTheCreatorsGroupsForANonAdmin(): void
    {
        $accessGroupRepository = new FakeAccessGroupRepository();
        $accessGroupRepository->groupIdsByUserId[7] = [10, 20];
        $resourceAccessRepository = new FakeResourceAccessRepository();

        $handler = $this->createHandler($accessGroupRepository, $resourceAccessRepository, userId: 7, isAdmin: false);

        $host = $this->createHost(name: 'server-01');
        $handler(new HostCreated($host, 7));

        self::assertCount(1, $resourceAccessRepository->grantedAccess);
        self::assertSame($host, $resourceAccessRepository->grantedAccess[0]['resource']);
        self::assertSame([10, 20], $resourceAccessRepository->grantedAccess[0]['accessGroupIds']);
        self::assertSame([10, 20], $accessGroupRepository->flaggedGroupIds);
        self::assertFalse($resourceAccessRepository->allResourcesFlaggedAsChanged);
    }

    public function testItFlagsAllResourcesForAnAdminWithoutSeedingCentreonAcl(): void
    {
        $accessGroupRepository = new FakeAccessGroupRepository();
        $resourceAccessRepository = new FakeResourceAccessRepository();

        $handler = $this->createHandler($accessGroupRepository, $resourceAccessRepository, userId: 1, isAdmin: true);

        $host = $this->createHost(name: 'server-02');
        $handler(new HostCreated($host, 1));

        self::assertTrue($resourceAccessRepository->allResourcesFlaggedAsChanged);
        self::assertSame([], $resourceAccessRepository->grantedAccess);
        self::assertSame([], $accessGroupRepository->flaggedGroupIds);
    }

    public function testItDoesNothingForANonAdminWithNoAccessGroup(): void
    {
        $accessGroupRepository = new FakeAccessGroupRepository();
        $resourceAccessRepository = new FakeResourceAccessRepository();

        $handler = $this->createHandler($accessGroupRepository, $resourceAccessRepository, userId: 9, isAdmin: false);

        $host = $this->createHost(name: 'server-03');
        $handler(new HostCreated($host, 9));

        self::assertSame([], $resourceAccessRepository->grantedAccess);
        self::assertSame([], $accessGroupRepository->flaggedGroupIds);
        self::assertFalse($resourceAccessRepository->allResourcesFlaggedAsChanged);
    }

    private function createHandler(
        FakeAccessGroupRepository $accessGroupRepository,
        FakeResourceAccessRepository $resourceAccessRepository,
        int $userId,
        bool $isAdmin,
    ): ReloadAclEventHandler {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId($userId), active: true);
        if ($isAdmin) {
            $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        }

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser($credential));

        return new ReloadAclEventHandler($security, $accessGroupRepository, $resourceAccessRepository);
    }

    private function createHost(string $name): Host
    {
        $host = new Host(
            id: null,
            name: new HostName($name),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId(1));

        return $host;
    }
}
