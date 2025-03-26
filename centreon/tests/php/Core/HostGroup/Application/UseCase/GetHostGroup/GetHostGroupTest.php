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

namespace Tests\Core\HostGroup\Application\UseCase\GetHostGroup;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\GetHostGroup\GetHostGroup;
use Core\HostGroup\Application\UseCase\GetHostGroup\GetHostGroupResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\TinyRule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new GetHostGroup(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readResourceAccessRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class),
         false,
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->useCaseSaas = new GetHostGroup(
        $this->readHostGroupRepository,
        $this->readHostRepository,
        $this->readAccessGroupRepository,
        $this->readResourceAccessRepository,
        $this->readContactGroupRepository,
        true,
        $this->user,
    );

    $this->hostGroup = new HostGroup(
        id: 1,
        name: 'hg-name',
        alias: 'hg-alias',
        geoCoords: GeoCoords::fromString('-2,100'),
    );
    $this->host = new SimpleEntity(
        id: 1,
        name: new TrimmedString('host-name'),
        objectName: 'Host',
    );

    $this->ruleA = new TinyRule(
        id: 1,
        name: 'rule-A',
    );
    $this->ruleB = new TinyRule(
        id: 2,
        name: 'rule-B',
    );
    $this->ruleC = new TinyRule(
        id: 3,
        name: 'rule-C',
    );
    $this->ruleA_bis = new TinyRule(
        id: 1,
        name: 'rule-A',
    );
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->hostGroup);
        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroup')
            ->willThrowException(new \Exception());

        $response = ($this->useCase)($this->hostGroup->getId());

        expect($response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())
            ->toBe(HostGroupException::errorWhileRetrieving()->getMessage());
    }
);

it(
    'should present a NotFoundResponse when no host group is found',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null);

        $response = ($this->useCase)($this->hostGroup->getId());

        expect($response)
            ->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present a GetHostGroupResponse as admin (OnPrem)',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->hostGroup);
        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroup')
            ->willReturn([]);

        $response = ($this->useCase)($this->hostGroup->getId());

        expect($response)
            ->toBeInstanceOf(GetHostGroupResponse::class)
            ->and($response->hostgroup)
            ->toBe($this->hostGroup);
    }
);

it(
    'should present a GetHostGroupResponse as non-admin user (OnPrem)',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->hostGroup);
        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroupAndAccessGroups')
            ->willReturn([$this->host]);

        $response = ($this->useCase)($this->hostGroup->getId());

        expect($response)
            ->toBeInstanceOf(GetHostGroupResponse::class)
            ->and($response->hostgroup)
            ->toBe($this->hostGroup)
            ->and($response->hosts)
            ->toBe([$this->host]);
    }
);

it(
    'should present a GetHostGroupResponse as non-admin user (Saas)',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->hostGroup);
        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroupAndAccessGroups')
            ->willReturn([$this->host]);
        $this->readResourceAccessRepository
            ->expects($this->once())
            ->method('findRuleByResourceIdAndContactId')
            ->willReturn([$this->ruleA, $this->ruleB]);
        $this->readResourceAccessRepository
            ->expects($this->once())
            ->method('findRuleByResourceIdAndContactGroups')
            ->willReturn([$this->ruleA_bis, $this->ruleC]);

        $response = ($this->useCaseSaas)($this->hostGroup->getId());

        expect($response)
            ->toBeInstanceOf(GetHostGroupResponse::class)
            ->and($response->hostgroup)
            ->toBe($this->hostGroup)
            ->and($response->hosts)
            ->toBe([$this->host])
            ->and($response->rules)
            ->toBe([0 => $this->ruleA, 1 => $this->ruleB, 3 => $this->ruleC]);
    }
);
