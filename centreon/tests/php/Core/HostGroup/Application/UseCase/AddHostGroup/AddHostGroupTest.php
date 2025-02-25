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

namespace Tests\Core\HostGroup\Application\UseCase\AddHostGroup;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroup;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupRequest;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupResponse;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupValidator;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostGroupRelation;
use Core\HostGroup\Domain\Model\NewHostGroup;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilter;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterValidator;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\MetaServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceCategoryFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceFilterType;
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\ServiceGroupFilterType;
use Core\ResourceAccess\Domain\Model\Rule;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new AddHostGroup(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->validator = $this->createMock(AddHostGroupValidator::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->isCloudPlatform = true,
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readResourceAccessRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->writeResourceAccessRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        $this->writeAccessGroupRepository = $this->createMock(WriteAccessGroupRepositoryInterface::class),
    );

    $this->addHostGroupRequest = new AddHostGroupRequest();
    $this->addHostGroupRequest->name = 'HG1';
    $this->addHostGroupRequest->alias = 'HG_Alias';
    $this->addHostGroupRequest->geoCoords = '-10,10';
    $this->addHostGroupRequest->comment = 'A New Hostgroup';
    $this->addHostGroupRequest->hosts = [1,2];
    $this->addHostGroupRequest->resourceAccessRules = [1,2,3];

    $this->datasetFilterValidator = $this->createMock(DatasetFilterValidator::class);
});

it(
    'Should return an InvalidArgumentResponse When an hostgroup already exists with this name',
    function (): void {
        $this->validator
            ->expects($this->once())
            ->method('assertNameDoesNotAlreadyExists')
            ->willThrowException(HostGroupException::nameAlreadyExists($this->addHostGroupRequest->name));

        $response = ($this->useCase)($this->addHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(HostGroupException::nameAlreadyExists($this->addHostGroupRequest->name)->getMessage());
    }
);

it(
    "Should return an InvalidArgumentResponse When a given host doesn't exist",
    function (): void {
        $this->validator
            ->expects($this->once())
            ->method('assertHostsExist')
            ->willThrowException(HostException::idsDoNotExist('hosts', [2]));

        $response = ($this->useCase)($this->addHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(HostException::idsDoNotExist('hosts', [2])->getMessage());
    }
);

it(
    "Should return an InvalidArgumentResponse When a given Resource Access Rule doesn't exist",
    function (): void {
        $this->validator
            ->expects($this->once())
            ->method('assertResourceAccessRulesExist')
            ->willThrowException(RuleException::idsDoNotExist('rules', [2]));

        $response = ($this->useCase)($this->addHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(RuleException::idsDoNotExist('rules', [2])->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when the "geoCoords" field value is not valid',
    function (): void {
        $this->addHostGroupRequest->geoCoords = 'this,is,wrong';

        $response = ($this->useCase)($this->addHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(InvalidGeoCoordException::invalidFormat()->getMessage());
    }
);

it (
    'should present an ErrorResponse when an error occured while creating the host group',
    function (): void {
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willThrowException(new \Exception());

            $response = ($this->useCase)($this->addHostGroupRequest);

            expect($response)
                ->toBeInstanceOf(ErrorResponse::class)
                ->and($response->getMessage())
                ->toBe(HostGroupException::errorWhileAdding()->getMessage());
    }
);

it(
    'should present an AddHostGroupResponse When everything is good',
    function (): void {
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn(7);

        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('addHosts');

        $this->readResourceAccessRepository
            ->expects($this->once())
            ->method('findLastLevelDatasetFilterByRuleIdsAndType')
            ->willReturn([
                [1 => [1,2,3]],
                [2 => [4,5,6]]
            ]);

        $this->writeResourceAccessRepository
            ->expects($this->exactly(2))
            ->method('updateDatasetResources');

        $hostGroup = new HostGroup(
            id: 7,
            name: $this->addHostGroupRequest->name,
            alias: $this->addHostGroupRequest->alias,
            notes: '',
            notesUrl: '',
            actionUrl: '',
            iconId: null,
            iconMapId: null,
            rrdRetention: null,
            geoCoords: GeoCoords::fromString($this->addHostGroupRequest->geoCoords),
            comment: $this->addHostGroupRequest->comment,
            isActivated: true
        );


        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($hostGroup);

        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroup')
            ->willReturn([
                new  SimpleEntity(1, new TrimmedString('host1'), 'Host'),
                new  SimpleEntity(2, new TrimmedString('host2'), 'Host')
            ]);

        $filterTypes = [];
        foreach ([
            HostFilterType::class,
            HostGroupFilterType::class,
            HostCategoryFilterType::class,
            ServiceFilterType::class,
            ServiceGroupFilterType::class,
            ServiceCategoryFilterType::class,
            MetaServiceFilterType::class,
        ] as $className) {
            $filterTypes[] = new $className();
        }

        $validator = new DatasetFilterValidator(new \ArrayObject($filterTypes));

        $this->readResourceAccessRepository
            ->expects($this->exactly(3))
            ->method('findById')
            ->willReturnOnConsecutiveCalls(
                new Rule(
                    id: 1,
                    name: 'rule1',
                    applyToAllContacts: true,
                    datasets: [new DatasetFilter('hostgroup', [1,2,3,7], $validator)]
                ),
                new Rule(
                    id: 2,
                    name: 'rule2',
                    applyToAllContacts: true,
                    datasets: [new DataSetFilter('hostgroup', [1,2,3,7], $validator)]
                ),
                new Rule(
                    id: 3,
                    name: 'rule3',
                    applyToAllContacts: true,
                    datasets: [new DataSetFilter('hostgroup', [1,2,3,7], $validator)]
                ),
            );

        dump($this->addHostGroupRequest);
        $response = ($this->useCase)($this->addHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(AddHostGroupResponse::class)
            ->and($response->getData())
            ->toBeInstanceOf(HostGroupRelation::class);
    }
);
