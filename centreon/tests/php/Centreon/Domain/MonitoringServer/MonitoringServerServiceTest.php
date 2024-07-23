<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Centreon\Domain\MonitoringServer;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\MonitoringServer\Exception\MonitoringServerException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\MonitoringServerService;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

beforeEach(function (): void {
    $this->user = $this->createMock(ContactInterface::class);
    $this->monitoringServerRepository = $this->createMock(MonitoringServerRepositoryInterface::class);
    $this->readAccessGroupsRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->monitoringServerService = new MonitoringServerService(
        $this->monitoringServerRepository,
        $this->readAccessGroupsRepository,
        $this->user
    );
    $this->central = (new MonitoringServer)->setId(1)->setName('Central');
    $this->poller = (new MonitoringServer)->setId(2)->setName('Poller');
    $this->monitoringServers = [$this->central, $this->poller];
});

it(
    'should throw an AccessDeniedException when the non-admin user does not have access to Monitoring Servers configuration page.',
    function (): void {
        $this->user
            ->expects($this->exactly(2))
            ->method('hasTopologyRole')
            ->willReturn(false);

        $this->monitoringServerService->findServers();
    }
)->throws(AccessDeniedException::class, 'You are not allowed to access this resource');

it('should throw a MonitoringServerException when an unhandled error is occured.', function (): void {
    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $accessGroup = new AccessGroup(2, 'nonAdmin', 'nonAdmin');

    $this->readAccessGroupsRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$accessGroup]);

    $this->monitoringServerRepository
        ->expects($this->once())
        ->method('findServersWithRequestParametersAndAccessGroups')
        ->with([$accessGroup])
        ->willThrowException(new \Exception());

    $this->monitoringServerService->findServers();
})->throws(MonitoringServerException::class, 'Error when searching for monitoring servers');

it('should return an array of monitoring servers when executed by an admin user.', function (): void {
    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->monitoringServerRepository
        ->expects($this->once())
        ->method('findServersWithRequestParameters')
        ->willReturn($this->monitoringServers);

    $monitoringServers = $this->monitoringServerService->findServers();

    expect($monitoringServers)->toBeArray();

    foreach ($monitoringServers as $index => $monitoringServer) {
        expect($monitoringServer->getId())
            ->toBe($this->monitoringServers[$index]->getId());
        expect($monitoringServer->getName())
            ->toBe($this->monitoringServers[$index]->getName());
    }
});

it('should return an array of monitoring servers when executed by a non-admin user.', function (): void {
    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->monitoringServerRepository
        ->expects($this->once())
        ->method('findServersWithRequestParametersAndAccessGroups')
        ->willReturn([$this->poller]);

    $monitoringServers = $this->monitoringServerService->findServers();

    expect($monitoringServers)->toBeArray();

    foreach ($monitoringServers as $monitoringServer) {
        expect($monitoringServer->getId())
            ->toBe($this->poller->getId());
        expect($monitoringServer->getName())
            ->toBe($this->poller->getName());
    }
});
